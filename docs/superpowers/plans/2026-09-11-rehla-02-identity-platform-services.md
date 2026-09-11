# Rehla Identity and Platform Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء الهوية والصلاحيات والتدقيق والوثائق والمسافرين وOutbox كخدمات منصة تعتمد عليها بقية مجالات رحلة.

**Architecture:** تملك كل حزمة جداولها ونماذجها وتعرض عقودًا صغيرة. ينفذ Audit وWallet لاحقًا حماية append-only في PostgreSQL، وتظل الملفات الخاصة معروفة بالمعرف فقط ولا تتسرب مسارات التخزين.

**Tech Stack:** Laravel Auth، Sanctum، PostgreSQL triggers وrow locks، Laravel Storage، Pest.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

## Global Constraints

- أكمل خطة Foundation أولًا.
- المنع هو نتيجة التفويض الافتراضية.
- لا يعيد أي Contract في هذه الخطة Eloquent Model إلى حزمة أخرى.
- كل عملية حساسة تسجل actor وsubject ووقتًا وmetadata منظفة من الأسرار.

---

### Task 1: Identity, Sessions and Capabilities

**Files:**
- Create: `packages/Rehla/Identity/database/migrations/*_create_identity_tables.php`
- Create: `packages/Rehla/Identity/src/Models/{User,StaffProfile,Role,Ability}.php`
- Create: `packages/Rehla/Identity/src/Enums/{AccountStatus,AbilityName}.php`
- Create: `packages/Rehla/Identity/src/Data/{ActorData,ResourceRef,RegisterCustomerData,UserData}.php`
- Create: `packages/Rehla/Identity/src/Actions/{RegisterCustomer,AssignRole,RevokeRole}.php`
- Create: `packages/Rehla/Identity/src/Queries/{GetCurrentUser,FindCustomer}.php`
- Create: `packages/Rehla/Identity/src/Contracts/AuthorizesActor.php`
- Modify: `config/auth.php`
- Test: `packages/Rehla/Identity/tests/Feature/IdentityTest.php`
- Test: `packages/Rehla/Identity/tests/Integration/AuthorizationTest.php`

**Interfaces:**
- Produces: `RegisterCustomer::handle(RegisterCustomerData): UserData`; `AuthorizesActor::allows(ActorData, AbilityName, ?ResourceRef): bool`.
- Produces the exact canonical registry in `specs/cross-cutting/security-and-privacy.md`; tests reject every undeclared alias.

- [ ] **Step 1: اكتب اختبارات التسجيل والمنع الافتراضي**

```php
it('registers a customer without staff powers', function (): void {
    $user = app(RegisterCustomer::class)->handle(new RegisterCustomerData(
        name: 'Ahmed Ali', email: 'ahmed@example.test', password: 'Secret-12345'
    ));

    expect($user->email)->toBe('ahmed@example.test')
        ->and(app(AuthorizesActor::class)->allows($user->actor, AbilityName::TopUpsReview))->toBeFalse();
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Identity/tests`

Expected: FAIL لأن الجداول والأنواع غير موجودة.

- [ ] **Step 3: أنشئ schema والعقود**

أنشئ `users(id uuid, name, email citext unique, password, status, email_verified_at, timestamps)`، و`staff_profiles(user_id unique, mfa_confirmed_at)`، و`roles`, `abilities`, `role_ability`, `user_role`. لا تستخدم عمود `is_admin`. اربط الصلاحيات بأسماء enum أعلاه، واحفظ passwords عبر Laravel Hash فقط.

```php
interface AuthorizesActor
{
    public function allows(ActorData $actor, AbilityName $ability, ?ResourceRef $resource = null): bool;
}
```

- [ ] **Step 4: أثبت عزل customer/admin guards وMFA policy**

اختبر أن customer session لا تدخل `/admin`، وأن staff بلا قدرة يحصل على403، وأن قدرات `topups.review`, `topups.settings.manage`, `access.manage`, `audit.view` تتطلب `mfa_confirmed_at` حديثة وفق نافذة أربع ساعات.

Run: `php artisan test packages/Rehla/Identity/tests`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Identity config/auth.php
git commit -m "feat(identity): add accounts roles and deny-by-default abilities"
```

### Task 2: Append-only Audit Trail

**Files:**
- Create: `packages/Rehla/Audit/database/migrations/*_create_audit_entries_table.php`
- Create: `packages/Rehla/Audit/database/migrations/*_protect_audit_entries.php`
- Create: `packages/Rehla/Audit/src/Data/AppendAuditData.php`
- Create: `packages/Rehla/Audit/src/Contracts/AuditWriter.php`
- Create: `packages/Rehla/Audit/src/Actions/AppendAuditEntry.php`
- Create: `packages/Rehla/Audit/src/Models/AuditEntry.php`
- Test: `packages/Rehla/Audit/tests/Integration/AuditImmutabilityTest.php`

**Interfaces:**
- Consumes: Core IDs وClock فقط.
- Produces: `AuditWriter::append(AppendAuditData): string` يعيد audit UUID.

- [ ] **Step 1: اكتب اختبار append والحماية المباشرة**

```php
it('cannot update or delete an audit entry even with direct SQL', function (): void {
    $id = app(AuditWriter::class)->append(new AppendAuditData(
        actorType: 'staff', actorId: fakeUuid(), action: 'top_up.approved',
        subjectType: 'top_up', subjectId: fakeUuid(), metadata: ['amount_minor' => 500000]
    ));

    expect(fn () => DB::table('audit_entries')->where('id', $id)->update(['action' => 'changed']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('audit_entries')->where('id', $id)->delete())
        ->toThrow(QueryException::class);
});
```

- [ ] **Step 2: شغل RED على PostgreSQL**

Run: `php artisan test packages/Rehla/Audit/tests/Integration/AuditImmutabilityTest.php`

Expected: FAIL قبل وجود الجدول/trigger.

- [ ] **Step 3: نفذ append-only storage**

أنشئ `audit_entries(id uuid, actor_type, actor_id nullable, action, subject_type, subject_id, metadata jsonb, ip_hash nullable, user_agent_hash nullable, occurred_at)` بلا `updated_at`. أضف PostgreSQL function واحدة ترفع exception على UPDATE أوDELETE وtrigger يستدعيها.

```php
interface AuditWriter
{
    public function append(AppendAuditData $data): string;
}
```

لا تخزن passwords أوtokens أومحتوى مستندات داخل metadata.

- [ ] **Step 4: شغل الاختبارات وراجع migration fresh**

Run: `php artisan migrate:fresh --env=testing && php artisan test packages/Rehla/Audit`

Expected: INSERT ينجح وUPDATE/DELETE يفشلان.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Audit
git commit -m "feat(audit): add immutable audit trail"
```

### Task 3: Private Document Lifecycle

**Files:**
- Create: `packages/Rehla/Documents/database/migrations/*_create_documents_tables.php`
- Create: `packages/Rehla/Documents/src/Enums/{DocumentStatus,DocumentPurpose}.php`
- Create: `packages/Rehla/Documents/src/Data/{BeginUploadData,DocumentRef}.php`
- Create: `packages/Rehla/Documents/src/Contracts/{DocumentScanner,OwnedDocuments}.php`
- Create: `packages/Rehla/Documents/src/Actions/{BeginUpload,StoreUpload,ScanDocument,AttachDocument,DeleteExpiredUploads}.php`
- Create: `packages/Rehla/Documents/src/Queries/AuthorizeDocumentDownload.php`
- Create: `packages/Rehla/Documents/src/Infrastructure/ClamAvDocumentScanner.php`
- Test: `packages/Rehla/Documents/tests/Feature/DocumentLifecycleTest.php`
- Test: `packages/Rehla/Documents/tests/Integration/DocumentRaceTest.php`

**Interfaces:**
- Produces: `OwnedDocuments::assertCleanOwned(array $documentIds, string $ownerId, DocumentPurpose $purpose): array<DocumentRef>`.
- Produces: private download response بعد Policy؛ لا ينتج storage path أوpublic URL.

- [ ] **Step 1: اكتب اختبار دورة الحالات والملكية**

```php
it('allows attachment only for a clean document owned by the account', function (): void {
    $document = uploadPrivatePdf(owner: $ownerA, purpose: DocumentPurpose::Passport);
    expect(fn () => app(OwnedDocuments::class)->assertCleanOwned([$document->id], $ownerB->id, DocumentPurpose::Passport))
        ->toThrow(DocumentAccessDenied::class);
    expect(fn () => attachDocument($document->id))->toThrow(DocumentNotClean::class);

    scanAsClean($document->id);
    expect(attachDocument($document->id)->status)->toBe(DocumentStatus::Attached);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Documents/tests`

Expected: FAIL لغياب lifecycle.

- [ ] **Step 3: أنشئ schema والتخزين الآمن**

أنشئ `upload_sessions(id, owner_id, purpose, expires_at, claimed_at)` و`documents(id, upload_session_id, owner_id, purpose, disk, storage_key, original_name, detected_mime, size_bytes, sha256, status, rejection_code, scanned_at, attached_at, timestamps)`. استخدم أسماء تخزين عشوائية ولا تستخدم اسم العميل في المسار.

الحالات المسموحة: `pending_scan → quarantined → clean|rejected → attached`. لا يوجد انتقال من rejected إلىclean؛ يعاد الرفع بمعرف جديد.

- [ ] **Step 4: تحقق من البايتات والتنظيف والسباق**

اختبر MIME معلنًا يخالف magic bytes، وملف polyglot، وPDF تالفًا، وملفًا أكبر من الحد، واستجابة تنزيل بـ`Content-Disposition: attachment`, `nosniff`, private cache headers. نفذ cleanup بقفل الصف وشرط `claimed_at is null`; نفذ attach بقفل الصف نفسه.

Run: `php artisan test packages/Rehla/Documents/tests`

Expected: PASS، وسباق cleanup/attach لا يحذف ملفًا attached.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Documents config/filesystems.php
git commit -m "feat(documents): secure private upload lifecycle"
```

### Task 4: Travelers and Passport Uniqueness

**Files:**
- Create: `packages/Rehla/Travelers/database/migrations/*_create_travelers_table.php`
- Create: `packages/Rehla/Travelers/src/Enums/Gender.php`
- Create: `packages/Rehla/Travelers/src/Data/{TravelerData,TravelerSnapshot}.php`
- Create: `packages/Rehla/Travelers/src/Actions/{CreateTraveler,UpdateTraveler}.php`
- Create: `packages/Rehla/Travelers/src/Queries/{ListOwnedTravelers,GetOwnedTravelerSnapshot}.php`
- Create: `packages/Rehla/Travelers/src/Support/NormalizePassportNumber.php`
- Test: `packages/Rehla/Travelers/tests/Feature/TravelerOwnershipTest.php`
- Test: `packages/Rehla/Travelers/tests/Integration/PassportUniquenessTest.php`

**Interfaces:**
- Produces: `GetOwnedTravelerSnapshot::handle(string $accountId, string $travelerId): TravelerSnapshot`.
- Produces snapshot: fullName،dateOfBirth،gender،passportNumber،passportIssuedAt،passportExpiresAt.

- [ ] **Step 1: اكتب اختبارات التطبيع والملكية**

```php
it('normalizes passport globally without revealing another owner', function (): void {
    createTraveler($ownerA, passport: ' p-12 34 ');

    expect(fn () => createTraveler($ownerB, passport: 'P1234'))
        ->toThrow(DuplicatePassport::class, ErrorCode::DuplicatePassport->value);
    expect(fn () => getTraveler($ownerB, travelerOf($ownerA)->id))->toThrow(TravelerNotFound::class);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Travelers/tests`

Expected: FAIL قبل schema/actions.

- [ ] **Step 3: نفذ traveler والـsnapshot**

أنشئ `travelers(id uuid, owner_id uuid, full_name, date_of_birth date, gender, passport_number, normalized_passport_number unique, passport_issued_at date, passport_expires_at date, timestamps)`. يطبق normalizer `mb_strtoupper` ثم يحذف Unicode whitespace و`-`. يمنع تاريخ إصدار بعد الانتهاء أوانتهاء قبل تاريخ الميلاد.

لا تضف nationality أوpassport country. أعد404 موحدة عند عدم الملكية لتجنب كشف المعرف.

- [ ] **Step 4: أثبت سباق uniqueness**

استخدم اتصالين PostgreSQL لإدخال الشكلين المطبعين نفسيهما، وتوقع نجاح واحد وخطأ domain واحد بلا500.

Run: `php artisan test packages/Rehla/Travelers/tests`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/Travelers
git commit -m "feat(travelers): add owned traveler profiles and passport uniqueness"
```

### Task 5: Transactional Outbox and In-app Notifications

**Files:**
- Create: `packages/Rehla/Notifications/database/migrations/*_create_notification_tables.php`
- Create: `packages/Rehla/Notifications/src/Data/OutboxMessageData.php`
- Create: `packages/Rehla/Notifications/src/Contracts/OutboxWriter.php`
- Create: `packages/Rehla/Notifications/src/Actions/{AppendOutboxMessage,ClaimOutboxBatch,MarkDelivered,MarkFailed,CreateInAppNotification,MarkNotificationRead}.php`
- Create: `packages/Rehla/Notifications/src/Models/{OutboxMessage,Notification}.php`
- Test: `packages/Rehla/Notifications/tests/Integration/OutboxTransactionTest.php`
- Test: `packages/Rehla/Notifications/tests/Integration/OutboxLeaseTest.php`
- Test: `packages/Rehla/Notifications/tests/Feature/InAppNotificationTest.php`

**Interfaces:**
- Produces: `OutboxWriter::append(OutboxMessageData): string` يعمل على اتصال ومعاملة المستدعي.
- Produces: `ClaimOutboxBatch::handle(int $limit, string $workerId, CarbonImmutable $now): array<OutboxEnvelope>`.

- [ ] **Step 1: اكتب اختبارات المعاملة والـlease**

```php
it('rolls back outbox with the business transaction', function (): void {
    try {
        DB::transaction(function (): void {
            app(OutboxWriter::class)->append(outboxData('top_up.approved', 'top-up-1'));
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {}

    expect(DB::table('outbox_messages')->count())->toBe(0);
});

it('commits the in-app notification with its business event', function (): void {
    createBusinessEventAndNotification();
    expect(DB::table('notifications')->count())->toBe(1);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/Notifications/tests`

Expected: FAIL قبل schema.

- [ ] **Step 3: نفذ outbox schema وclaim**

أنشئ `outbox_messages(id, event_name, aggregate_type, aggregate_id, payload_version, payload jsonb, deduplication_key unique, available_at, locked_at, locked_by, lock_token, lease_expires_at, attempts default0, delivered_at, last_error, last_trace_id, created_at)` و`notifications(id, user_id, type, payload, read_at, created_at)`. ينشأ in-app notification مع العملية، ويستخدم claim معاملة قصيرة و`FOR UPDATE SKIP LOCKED` ويولد token جديدًا عند كل claim أو استعادة lease منتهية.

- [ ] **Step 4: أثبت التنافس والاسترداد**

باستخدام اتصالين، توقع ألا يطالب عاملان بالسجل نفسه. قدم clock بعد انتهاء lease وتوقع claim جديدًا وtoken مختلفًا، ثم أثبت أن العامل القديم لا يستطيع MarkDelivered أو MarkFailed. ينقل الفشل الخامس الرسالة إلى dead-letter دون حذفها، ويحفظ error منظفًا وtrace ID فقط.

Run: `php artisan test packages/Rehla/Notifications/tests`

Expected: PASS.

- [ ] **Step 5: حدث سجل القبول وبوابة الخطة**

Run: `composer verify && git diff --check`

Expected: PASS للهوية والتدقيق والوثائق والمسافرين والإشعارات وكل اختبارات المعمارية.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Notifications docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(notifications): add transactional outbox foundation"
```

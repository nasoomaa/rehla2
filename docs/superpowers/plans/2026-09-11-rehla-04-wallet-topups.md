# Rehla Wallet and Top-ups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء محفظة صحيحة محاسبيًا وطلبات شحن بنكي لا يمكن اعتمادها أوقيدها مرتين.

**Architecture:** يملك Wallet الرصيد والدفتر ويعرض Credit/Debit contracts فقط. تملك TopUps الحسابات البنكية وطلبات التحويل، وتنفذ الاعتماد في معاملة واحدة تشمل قفل الطلب والمحفظة وCredit وAudit وOutbox.

**Tech Stack:** PostgreSQL bigint/check constraints/row locks/triggers، Laravel transactions، Pest integration tests باتصالين.

**Spec:** `docs/REHLA-LARAVEL-PACKAGE-ARCHITECTURE.md`

**Prerequisites:** إغلاق بوابة الخطة 03 وتوفر Identity وAudit وDocuments وNotifications foundation؛ تغلق Task 1 بوابة registration-atomicity المفتوحة من الخطة 02.

## Global Constraints

- المبلغ integer minor units وعملة `SDG` فقط.
- ledger append-only؛ التصحيح قيد جديد بعلاقة `reverses_entry_id`.
- لا تستخدم cache أوqueue لتقرير الرصيد.
- لا تختبر concurrency داخل wrapper transaction واحد.

---

### Task 1: Wallet and Append-only Ledger

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: Wallet. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R15`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/Wallet/src/database/migrations/*_create_wallet_tables.php`
- Create: `packages/Rehla/Wallet/src/database/migrations/*_protect_wallet_ledger.php`
- Create: `packages/Rehla/Wallet/src/database/migrations/*_create_wallet_reconciliation_runs_table.php`
- Create: `packages/Rehla/Wallet/src/Enums/LedgerEntryType.php`
- Create: `packages/Rehla/Wallet/src/Data/{WalletBalance,WalletEntryData,DebitResult,CreditResult}.php`
- Create: `packages/Rehla/Wallet/src/Contracts/{WalletReader,WalletCreditor,WalletDebitor}.php`
- Create: `packages/Rehla/Wallet/src/Actions/{OpenWallet,CreditWallet,DebitWallet,ReverseWalletEntry}.php`
- Create: `packages/Rehla/Wallet/src/Infrastructure/IdentityRegistrationWalletInitializer.php`
- Modify: `packages/Rehla/Wallet/src/Providers/WalletServiceProvider.php`
- Create: `packages/Rehla/Wallet/src/Queries/{GetWalletBalance,ListWalletEntries}.php`
- Test: `packages/Rehla/Wallet/tests/Integration/WalletLedgerTest.php`
- Test: `packages/Rehla/Wallet/tests/Integration/ConcurrentDebitTest.php`

**Mandatory Package Contract — Wallet:**
- Create/verify: `packages/Rehla/Wallet/composer.json` and `packages/Rehla/Wallet/README.md`.
- Create/verify: `packages/Rehla/Wallet/src/Providers/WalletServiceProvider.php`.
- Create/verify: `packages/Rehla/Wallet/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/Wallet/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/Wallet/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-wallet`; `WalletServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-wallet')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/Wallet/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Implements: `Rehla\Identity\Contracts\RegistrationWalletInitializer`؛ ينشئ محفظة الحساب داخل معاملة التسجيل المستدعية.
- Produces: `WalletCreditor::credit(CreditWalletData): CreditResult`.
- Produces: `WalletDebitor::debit(DebitWalletData): DebitResult`.
- البيانات تشمل `accountId`, `amount: Money`, `referenceType`, `referenceId`, `idempotencyKey`.

- [ ] **Step 1: اكتب اختبارات الرصيد والدفتر**

```php
it('derives every balance change from one immutable ledger entry', function (): void {
    $wallet = openWallet($accountId);
    credit($wallet, 5_000_00, 'top_up', 'topup-1');
    debit($wallet, 2_500_00, 'order', 'order-1');

    expect(walletBalance($wallet)->minor)->toBe(2_500_00)
        ->and(walletEntries($wallet))->toHaveCount(2);
    $entryId = DB::table('wallet_ledger_entries')->value('id');
    expect(fn () => DB::table('wallet_ledger_entries')->where('id', $entryId)->delete())
        ->toThrow(QueryException::class);
});

it('creates the account and wallet atomically during registration', function (): void {
    $user = registerCustomer();
    expect(DB::table('wallets')->where('account_id', $user->id)->value('balance_minor'))->toBe(0);

    failNextWalletInsert();
    expect(fn () => registerCustomer(email: 'rollback@example.test'))->toThrow(QueryException::class)
        ->and(DB::table('users')->where('email', 'rollback@example.test')->exists())->toBeFalse();
});
```

- [ ] **Step 2: شغل RED على PostgreSQL**

Run: `php artisan test packages/Rehla/Wallet/tests/Integration/WalletLedgerTest.php`

Expected: FAIL قبل schema.

- [ ] **Step 3: أنشئ schema والـcontracts**

```text
wallets: id uuid, account_id uuid unique, currency char(3), balance_minor bigint,
         lock_version bigint, created_at, updated_at
wallet_ledger_entries: id uuid, wallet_id, type, amount_minor bigint,
         balance_after_minor bigint, reference_type, reference_id,
         idempotency_key, reverses_entry_id nullable, created_at
wallet_reconciliation_runs: id uuid, started_at, completed_at, checked_wallets,
         mismatch_count, status, evidence_path
```

أضف checks للعملة وamount الموجب وbalance غير السالب، وunique `(wallet_id,reference_type,reference_id,type)` و`(wallet_id,idempotency_key)`. يمنع trigger UPDATE وDELETE على ledger. تقفل Credit وDebit صف wallet بـ`FOR UPDATE` وتضيف ledger وتحدث balance في المعاملة الموجودة دون فتح commit مستقل. يطبق `IdentityRegistrationWalletInitializer` منفذ Identity ويستخدم `OpenWallet` idempotently ثم يربطه `WalletServiceProvider`. أثبت أن فشل wallet أوnotification أوaudit يرجع المستخدم والمحفظة والإشعار معًا.

- [ ] **Step 4: أثبت التزامن والـidempotency**

ابدأ رصيدًا 2,500 SDG، وشغل Debitين متوازيين كل منهما 2,500. توقع نتيجة ناجحة واحدة و`INSUFFICIENT_BALANCE` للثانية، balance=0 وقيد debit واحد. أعد نفس idempotency key وتوقع نفس `DebitResult` بلا قيد إضافي.

Run: `php artisan test packages/Rehla/Wallet/tests/Integration/ConcurrentDebitTest.php`

Expected: PASS على اتصالين مستقلين.

- [ ] **Step 5: اختبر التصحيح والتسوية**

اختبر أن `ReverseWalletEntry` يضيف قيدًا معاكسًا ولا يغير القديم، وأن مجموع entries يساوي `wallets.balance_minor`.

Run: `php artisan test packages/Rehla/Wallet`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/Rehla/Wallet docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(wallet): add locked append-only wallet ledger"
```

### Task 2: Bank Accounts and Top-up Submission

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: TopUps. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R16, R17, R18, R56`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/TopUps/src/database/migrations/*_create_top_up_tables.php`
- Create: `packages/Rehla/TopUps/src/Enums/TopUpStatus.php`
- Create: `packages/Rehla/TopUps/src/Data/{BankAccountData,SubmitTopUpData,TopUpData}.php`
- Create: `packages/Rehla/TopUps/src/Actions/{CreateBankAccount,UpdateBankAccount,DeactivateBankAccount,SubmitTopUp}.php`
- Create: `packages/Rehla/TopUps/src/Queries/{ListActiveBankAccounts,ListOwnedTopUps,GetTopUpForReview}.php`
- Create: `packages/Rehla/TopUps/src/Support/NormalizeTransactionReference.php`
- Create: `packages/Rehla/TopUps/src/Actions/{ReplaceTopUpReceipt,ConfigureMinimumTopUp}.php`
- Test: `packages/Rehla/TopUps/tests/Feature/BankAccountTest.php`
- Test: `packages/Rehla/TopUps/tests/Integration/SubmitTopUpTest.php`

**Mandatory Package Contract — TopUps:**
- Create/verify: `packages/Rehla/TopUps/composer.json` and `packages/Rehla/TopUps/README.md`.
- Create/verify: `packages/Rehla/TopUps/src/Providers/TopUpsServiceProvider.php`.
- Create/verify: `packages/Rehla/TopUps/src/resources/lang/en/messages.php`.
- Create/verify: `packages/Rehla/TopUps/src/resources/lang/ar/messages.php`.
- Create/verify: `packages/Rehla/TopUps/tests/Architecture/TranslationCompletenessTest.php`.
- Translation namespace: `rehla-topups`; `TopUpsServiceProvider` must call `loadTranslationsFrom(__DIR__.'/../resources/lang', 'rehla-topups')`.
- يبدأ ملفا `messages.php` بمصفوفتين متطابقتين ولو كانتا فارغتين. يضيف أي نص عام مفتاحي EN/AR في الالتزام نفسه، ويمنع الاختبار اختلاف المفاتيح أوشكل scalar/array والنص المرئي الصريح في PHP.
- يوثق README العقود العامة، التفويض deny-by-default، حدود المعاملة والـexternal I/O، error codes العامة، owned tables، وخطة الاستعادة. لا تعيد العقود Models قابلة للتعديل ولا تنفذ الحزمة commit داخليًا عند انضمامها إلى معاملة المالك.
- Acceptance coverage: `سجل القبول الذري المرتبط بعقود هذه الحزمة`. يبدأ التنفيذ بـRED محدد، ثم `php artisan test packages/Rehla/TopUps/tests`، ثم `php artisan test packages/Rehla tests/Architecture`، ثم formatter وإعادة الاختبارات المتأثرة قبل commit.

**Interfaces:**
- Produces: `SubmitTopUp::handle(SubmitTopUpData): TopUpData`.
- Consumes: `OwnedDocuments` للتحقق من receipt clean/owned/classification=`bank_receipt`.

- [ ] **Step 1: اكتب اختبار الإرسال والتفرد**

```php
it('rejects a reused reference for the same bank after normalization', function (): void {
    submitTopUp($ownerA, bank: $bank, reference: ' ab-12 34 ', amountMinor: 5_000_00, receipt: cleanReceipt($ownerA));

    expect(fn () => submitTopUp($ownerB, bank: $bank, reference: 'AB1234', amountMinor: 5_000_00, receipt: cleanReceipt($ownerB)))
        ->toThrow(TransactionReferenceUsed::class);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/TopUps/tests/Integration/SubmitTopUpTest.php`

Expected: FAIL قبل schema/actions.

- [ ] **Step 3: أنشئ schema**

```text
bank_accounts: id, bank_name_en/ar, beneficiary_name, account_number,
               logo_document_id, active, sort_order, created_by, timestamps
top_up_settings: id singleton, minimum_amount_minor, updated_by, updated_at
top_up_requests: id, account_id, wallet_id, bank_account_id, amount_minor,
                 minimum_amount_minor_at_submission, transaction_reference,
                 normalized_reference, receipt_document_id,
                 status, submitted_at, reviewed_by nullable, decided_at nullable,
                 rejection_reason nullable, credit_ledger_entry_id nullable, timestamps
```

أضف unique `(bank_account_id,normalized_reference)`، وchecks للمبلغ والحالة. لا تعيد رسالة التفرد مالك المرجع السابق.

- [ ] **Step 4: نفذ SubmitTopUp**

اقفل إعداد الحد الأدنى الحالي (قيمته الأولية `5000_00`) واحفظه مع الطلب، ثم تحقق من بنك active والمبلغ والreceipt clean/owned وأضف request بحالة `under_review`. ينفذ `ConfigureMinimumTopUp` بقدرة `topups.settings.manage` وMFA وAudit. يسمح `ReplaceTopUpReceipt` للمالك باستبدال receipt نظيف داخل الطلب نفسه ما دام `under_review` مع Audit للقديم والجديد؛ لا يغير البنك أو المرجع ولا يسمح بعد القرار. تعطيل البنك يمنع الطلبات الجديدة ويترك التاريخ.

Run: `php artisan test packages/Rehla/TopUps/tests`

Expected: PASS للبنوك والإرسال والملكية والحد الأدنى.

- [ ] **Step 5: Commit**

```bash
git add packages/Rehla/TopUps docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(topups): add banks and transfer submission"
```

### Task 3: Atomic Approval and Rejection

**Task Completeness Contract:**
- **Files:** القائمة التالية exhaustive لهذه المهمة؛ أي ملف إنتاجي إضافي يحدث الخطة وسجل القبول أولًا.
- **Contracts:** قسم Interfaces أدناه يحدد المدخلات والمخرجات؛ لا Models قابلة للتعديل ولا عقد غير مسجل في خريطة الحواف.
- **Database ownership:** `table-ownership.json` هو المرجع؛ النطاق المكتشف: TopUps. لا migration أوكتابة خارج المالك.
- **Authorization:** deny-by-default مع owner/other-account وstaff ability حيث ينطبقان، ولا تعتمد الحماية على الواجهة وحدها.
- **Localization:** كل نص ظاهر يستخدم مفاتيح EN/AR متكافئة في حزمة المالك، ورموز API محايدة لغويًا.
- **Error codes:** أخطاء المجال العامة lower dot notation ومسجلة في Core/Problem Details؛ لا رسائل أوexceptions داخلية كهوية عامة.
- **Transaction boundary:** Action المنسقة تعلن مالك المعاملة، ويشارك providers الاتصال نفسه بلا commit داخلي؛ القراءة البحتة تعلن غياب الكتابة.
- **External I/O:** ممنوع داخل معاملة الأعمال؛ تسجل القنوات المطلوبة في Outbox ثم تنفذ بعد commit مع retries وfencing.
- **Privacy:** أقل DTO وحقول لازمة، 404 غير كاشف للعميل، وقدرة صريحة للموظف، ولا storage keys أوsecrets أوinternal notes.
- **RED:** أول خطوة سلوكية تشغل اختبارًا يفشل للسبب المتوقع المحدد، لا بسبب bootstrap أوfixture مكسور.
- **GREEN:** أقل تنفيذ ينجح الاختبار المركز مع PostgreSQL عندما توجد معاملة أوقيد أوتزامن.
- **Expanded verification:** اختبارات الحزمة والمستهلكين وArchitecture ثم formatter وإعادة الاختبارات المتأثرة؛ لا يغلق الصف من اختبار مركز فقط.
- **Acceptance IDs:** `R19, R20, R21, R44, R57`؛ يسجل كل ID command وtest name ونتيجة ومسار artifact قبل `verified`.
- **Recovery:** تعطيل المسار أوforward-only correction للسجلات الثابتة؛ لا rollback مدمر لـLedger/Audit/Orders/FormVersions أوblobs مرتبطة.
- **Commit:** الالتزام المحدد آخر المهمة بعد GREEN والتحقق الموسع و`git diff --check`، ولا يضم تغييرات مهمة أخرى.


**Files:**
- Create: `packages/Rehla/TopUps/src/Data/{ApproveTopUpData,RejectTopUpData}.php`
- Create: `packages/Rehla/TopUps/src/Actions/{ApproveTopUp,RejectTopUp}.php`
- Create: `packages/Rehla/TopUps/src/Events/{TopUpApproved,TopUpRejected}.php`
- Test: `packages/Rehla/TopUps/tests/Integration/ApproveTopUpTest.php`
- Test: `packages/Rehla/TopUps/tests/Integration/ConcurrentApprovalTest.php`
- Test: `packages/Rehla/TopUps/tests/Feature/RejectTopUpTest.php`

**Interfaces:**
- Consumes: `WalletCreditor`, `AuditWriter`, `OutboxWriter`, `AuthorizesActor`.
- Produces: approved/rejected `TopUpData`؛ replay المطابق يعيد النتيجة نفسها.

- [ ] **Step 1: اكتب اختبار atomic approval**

```php
it('credits, approves, audits and enqueues atomically', function (): void {
    $topUp = underReviewTopUp(amountMinor: 5_000_00);
    approveTopUp($reviewer, $topUp);

    expect(topUp($topUp)->status)->toBe(TopUpStatus::Approved)
        ->and(walletBalanceFor($topUp->accountId))->toBe(5_000_00)
        ->and(creditEntriesFor($topUp))->toHaveCount(1)
        ->and(auditEntriesFor($topUp))->toHaveCount(1)
        ->and(outboxFor($topUp, 'top_up.approved'))->toHaveCount(1);
});
```

- [ ] **Step 2: شغل RED**

Run: `php artisan test packages/Rehla/TopUps/tests/Integration/ApproveTopUpTest.php`

Expected: FAIL قبل ApproveTopUp.

- [ ] **Step 3: نفذ ترتيب القفل والمعاملة**

```php
return DB::transaction(function () use ($data): TopUpData {
    $topUp = $this->topUps->lockForUpdate($data->topUpId);
    $this->authorization->assert($data->actor, AbilityName::TopUpsReview);
    if ($topUp->isApproved()) return $topUp->toData();
    if (! $topUp->isUnderReview()) throw InvalidTopUpTransition::from($topUp);

    $credit = $this->wallets->credit(CreditWalletData::forTopUp($topUp));
    $topUp->approve($data->actor->id, $credit->entryId, $this->clock->now());
    $this->audit->append(AppendAuditData::topUpApproved($data, $topUp, $credit));
    $this->outbox->append(OutboxMessageData::topUpApproved($topUp));
    return $topUp->toData();
});
```

يلتزم Wallet بالاتصال والمعاملة الحاليين. لا ترسل notification مباشرة.

- [ ] **Step 4: أثبت التكرار والتزامن والفشل**

شغل اعتمادين باتصالين وتوقع Credit واحدًا. حقن exception بعد credit وقبل تحديث request، وبعد Audit وقبل Outbox؛ توقع rollback كامل في الحالتين. إعادة approve بعد نجاحه تعيد approved بلا أثر جديد.

Run: `php artisan test packages/Rehla/TopUps/tests/Integration/ConcurrentApprovalTest.php`

Expected: PASS.

- [ ] **Step 5: نفذ الرفض**

يتطلب الرفض سببًا غير فارغ، يقفل request، يغير `under_review→rejected`، يسجل reviewer/time/reason وAudit وOutbox، ولا يستدعي Wallet. replay يعيد النتيجة بلا سجل جديد.

Run: `php artisan test packages/Rehla/TopUps/tests/Feature/RejectTopUpTest.php`

Expected: PASS ورصيد ثابت.

- [ ] **Step 6: بوابة الخطة وCommit**

Run: `composer verify && git diff --check`

Expected: PASS لكل اختبارات PostgreSQL والتزامن.

```bash
git add packages/Rehla/TopUps docs/requirements/rehla-phase-1-acceptance.csv
git commit -m "feat(topups): make transfer decisions atomic and idempotent"
```

## Plan Completion Gate

تغلق الخطة Wallet ledger وTopUps submission/review. تغلق `registration-atomicity` باختبار تكامل حقيقي على PostgreSQL يثبت أن account وAudit وwallet وwelcome notification وOutbox تتراجع معًا عند فشل أي مشارك. تنتج عقود الرصيد والخصم والائتمان وقراءات الشحن للخطة 05.

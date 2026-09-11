# Journey 01: Service Discovery and WhatsApp Inquiry

## 1. Goal

A user (guest or customer) browses available travel offerings on Rehla, inspects service descriptions, validity rules, expected turnaround times, and current authoritative prices, and initiates an inquiry via WhatsApp without creating any orders, holds, or financial debits.

---

## 2. Actors

- **User (Guest or Logged-in Customer)**
- **Rehla Public Web / API Interface**
- **External WhatsApp Web / Application**

---

## 3. Preconditions

1. At least one travel service (e.g. "UAE 30-Day Tourist Visa") is published in `active` status in the Service Catalog.
2. An active form version is linked to the service.
3. Customer care WhatsApp phone number is configured in platform settings.

---

## 4. Trigger

The user navigates to the Rehla homepage (`https://rehla.sd` or opens mobile application).

---

## 5. Complete Happy-Path Sequence

1. **User opens Rehla homepage**:
   - The browser requests `GET /`.
   - The system loads the public storefront.
   - The storefront displays the catalog banner, featured travel categories, and published services ordered by `display_order`.
2. **User browses service catalog**:
   - The user views cards displaying service names, marketing imagery, short descriptions, and starting prices in Sudanese Pounds (e.g. `"25,000.00 SDG"` / `"25,000.00 ج.س"`).
3. **User selects a specific service**:
   - The user clicks on the card for "UAE 30-Day Tourist Visa".
   - The browser requests `GET /services/uae-30-day-visa`.
4. **System renders service details page**:
   - The system displays:
     - High-resolution marketing images.
     - Full service title and comprehensive description.
     - Authoritative price in SDG (`25,000.00 SDG`).
     - Estimated processing duration (e.g. `"3 to 5 business days"`).
     - Informational requirements checklist (e.g. `"Passport valid for at least 6 months"`, `"White background personal photo"`).
     - Primary call-to-action button: **"Order Now"**.
     - Secondary inquiry button: **"Inquire via WhatsApp"**.
5. **User clicks "Inquire via WhatsApp"**:
   - The user decides to ask a question regarding document attestation before purchasing.
   - The user clicks the WhatsApp button.
6. **System constructs secure deep link**:
   - The system generates the deep-link URL:
     `https://wa.me/249912345678?text=Hello%20Rehla%20Support%2C%20I%20would%20like%20to%20inquire%20about%20the%20service%3A%20%22UAE%2030-Day%20Tourist%20Visa%22%20%28Price%3A%2025%2C000.00%20SDG%29.`
   - The browser opens the link in a new window/tab with `rel="noopener noreferrer"`.
7. **Handoff to external messaging**:
   - The user's device launches WhatsApp with the pre-filled message text.
   - The user sends the message to Rehla's support agent.

---

## 6. Alternate Paths

### Path 1A: User Switches Language to Arabic
1. On the service details page, the user toggles language to Arabic (`ar`).
2. The page re-renders with `dir="rtl"` layout.
3. The WhatsApp button updates its generated deep-link text to Arabic:
   `https://wa.me/249912345678?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%D9%8B%20%D9%81%D8%B1%D9%8A%D9%82%20%D8%B1%D8%AD%D9%84%D8%A9%D8%8C%20%D8%A3%D9%88%D8%AF%20%D8%A7%D9%84%D8%A7%D8%B3%D8%AA%D9%81%D8%B3%D8%A7%D8%B1%20%D8%B9%D9%86%20%D8%AE%D8%AF%D9%85%D8%A9%3A%20%22%D8%AA%D8%A3%D8%B4%D9%8A%D8%B1%D8%A9%20%D8%A7%D9%84%D8%A5%D9%85%D8%A7%D8%B1%D8%A7%D8%AA%2030%20%D9%8A%D9%88%D9%85%D8%A7%D9%8B%22%20%28%D8%A7%D9%84%D8%B3%D8%B9%D8%B1%3A%2025%2C000.00%20%D8%AC.%D8%B3%29.`
4. Clicking opens WhatsApp with Arabic text.

---

## 7. Failure Paths

### Path 1F: Service Inactive or Deactivated
1. The user attempts to navigate to a deactivated service slug via a saved bookmark: `GET /services/old-expired-visa`.
2. The system queries the Service Catalog and determines the service status is `inactive` or `draft`.
3. The system returns HTTP 404 Not Found (or renders a clear page: `"This service is currently unavailable for ordering"`).
4. No WhatsApp button or "Order Now" button is rendered.

---

## 8. Recovery Behavior

- If WhatsApp is not installed on the user’s device, WhatsApp Web falls back to a browser prompt allowing the user to download WhatsApp or use WhatsApp Web.
- The user can return to the Rehla browser tab at any time; no state on Rehla was created or altered.

---

## 9. Final Observable State

1. **Zero Database State Change**:
   - `orders` table: unchanged (0 records created).
   - `service_executions` table: unchanged (0 records created).
   - `wallets` table: unchanged (0 balance deducted).
   - `wallet_ledger_entries` table: unchanged (0 entries).
2. **User State**:
   - User is in external WhatsApp conversation with Rehla support with pre-filled service context.

---

## 10. Cross-Domain Dependencies

- **Service Catalog**: Provides service descriptions, prices, media, and requirements.
- **Content**: Provides localized UI chrome and RTL layout templates.

---

## 11. External-System Interactions

- **WhatsApp (Meta)**: Browser executes external URL redirect to `https://wa.me/...`.

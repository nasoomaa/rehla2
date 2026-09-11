# Contract: WhatsApp Inquiry and External Handoff

## 1. Responsibility

This contract defines the operational boundary, formatting rules, and strict zero-side-effect guarantees for customer interactions via WhatsApp. It ensures that clicking the WhatsApp action on any service page functions purely as an informational handoff to external chat support, completely isolated from platform financial and ordering state.

---

## 2. Producer and Consumer

- **Producer**: Rehla Presentation Layer (`packages/Rehla/Web`, `packages/Rehla/Api`).
- **Consumer**: Customer’s Native WhatsApp Client, WhatsApp Web, or Browser Deep-Link Handler.

---

## 3. Strict Zero-Side-Effect Invariant

Clicking the **"Inquire via WhatsApp"** button:
1. **MUST NOT** create a commercial order.
2. **MUST NOT** debit the customer’s wallet.
3. **MUST NOT** place a hold or reservation on wallet funds.
4. **MUST NOT** create a service execution record.
5. **MUST NOT** initiate an application form draft.
6. **MUST NOT** require customer authentication or login.

It is strictly an informational URL generation feature with zero persistent platform database mutation.

---

## 4. Deep-Link Format Specification

The WhatsApp deep link is constructed deterministically from platform support settings and service metadata:

### URL Structure:
```text
https://wa.me/{configured_support_phone_e164}?text={url_encoded_inquiry_message}
```

### Parameters:
- `phone`: Configured platform customer care phone number in strict E.164 international format (e.g. `249912345678` for Sudan, digits only, no `+` sign).
- `text`: URL-encoded pre-filled message containing the service name and inquiry intent.

---

## 5. Message Templates by Locale

### English (`en`):
```text
Hello Rehla Support, I would like to inquire about the service: "{service_name}" (Price: {formatted_price}).
```
**Example Encoded URL**:
```text
https://wa.me/249912345678?text=Hello%20Rehla%20Support%2C%20I%20would%20like%20to%20inquire%20about%20the%20service%3A%20%22UAE%2030-Day%20Tourist%20Visa%22%20%28Price%3A%2025%2C000.00%20SDG%29.
```

### Arabic (`ar`):
```text
مرحباً فريق رحلة، أود الاستفسار عن خدمة: "{service_name}" (السعر: {formatted_price}).
```
**Example Encoded URL**:
```text
https://wa.me/249912345678?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%D9%8B%20%D9%81%D8%B1%D9%8A%D9%82%20%D8%B1%D8%AD%D9%84%D8%A9%D8%8C%20%D8%A3%D9%88%D8%AF%20%D8%A7%D9%84%D8%A7%D8%B3%D8%AA%D9%81%D8%B3%D8%A7%D8%B1%20%D8%B9%D9%86%20%D8%AE%D8%AF%D9%85%D8%A9%3A%20%22%D8%AA%D8%A3%D8%B4%D9%8A%D8%B1%D8%A9%20%D8%A7%D9%84%D8%A5%D9%85%D8%A7%D8%B1%D8%A7%D8%AA%2030%20%D9%8A%D9%88%D9%85%D8%A7%D9%8B%22%20%28%D8%A7%D9%84%D8%B3%D8%B9%D8%B1%3A%2025%2C000.00%20%D8%AC.%D8%B3%29.
```

---

## 6. Security and Verification

1. **Rel Security**: All HTML anchor tags rendering the WhatsApp link must include `target="_blank" rel="noopener noreferrer"` to prevent window opener exploits.
2. **Dynamic Price Reflection**: The deep link must dynamically reflect the active authoritative price at page render time, but carries zero legal binding commitment if prices change later.

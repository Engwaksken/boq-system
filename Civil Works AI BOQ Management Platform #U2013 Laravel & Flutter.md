# Civil Works AI BOQ Management Platform

I want to develop a **multilingual Civil Works BOQ Management, AI Costing and Subscription Platform** with:

- **Laravel web application**
- **Flutter mobile application**
- Laravel REST API shared with Flutter
- MySQL database
- AI-assisted BOQ extraction, analysis and pricing
- Excel, PDF, image and camera/scanner import
- Multi-currency support
- Multilingual support, initially including English and Luganda, with the ability to add more languages later
- Subscription and licensing management
- One-time, monthly, three-month, six-month, annual and lifetime access plans
- Paid feature updates through a top-up system

The platform will be used for civil works, construction projects, renovations, infrastructure projects and related procurement and cost-estimation activities.

Use the uploaded **Lot - 8 - BoQ.xlsx** as one of the reference BOQ structures when designing the system.

The existing Excel demonstrates that a BOQ may contain:

- Multiple facilities
- Multiple bills
- Preliminaries
- Elements
- Sub-elements
- Item codes
- Description
- Unit
- Quantity
- Rate
- Amount
- Bill summaries
- Facility summaries
- Grand totals

The system must support this hierarchical structure rather than treating every uploaded spreadsheet as a simple flat table.

---

# 1. Overall Architecture

Use:

### Backend/Web

- Laravel
- MySQL
- Blade
- Bootstrap 5
- Font Awesome
- REST API
- Laravel localization and translation files
- UTF-8 and Unicode support throughout the application
- Subscription and entitlement management
- Payment and transaction management
- Feature-update and top-up management

### Mobile

- Flutter
- Same Laravel API
- Secure token authentication
- Flutter localization using ARB or equivalent localization files
- Unicode-compatible fonts and text rendering
- Subscription status and entitlement display
- Payment and top-up access through the Laravel API

### AI

The AI provider/API must be configurable by the administrator.

Do not hard-code the AI provider inside the application.

Admin should be able to configure:

- AI provider
- API key
- AI model
- OCR model/provider where applicable
- Enable/disable AI
- Maximum tokens/request limits
- AI pricing analysis settings
- AI translation settings
- Supported AI processing languages
- Default source and target languages

The architecture should make it possible to change between providers later without rewriting the whole platform.

All user-facing text must be translatable. Do not hard-code labels, buttons, validation messages, notifications, emails, reports or system instructions directly into application code.

---

# 2. Subscription and Licensing Model

The platform must support paid user access through multiple subscription and licensing options.

Users should be able to purchase:

- One-time access
- Monthly subscription
- Three-month subscription
- Six-month subscription
- Annual subscription
- Lifetime access

The system must distinguish between:

```text
Subscription Access
      ↓
Feature Entitlements
      ↓
Feature Updates and Top-Ups
```

A user may have valid access to the platform but may not automatically receive every new feature released after their purchase.

New features and major updates should be managed separately through a **Feature Update Top-Up System**.

---

# 3. Subscription Plans

Create an Admin-managed subscription plan system.

Each plan should support:

- Plan name
- Plan code
- Description
- Translated name
- Translated description
- Plan type
- Duration
- Price
- Currency
- Active/inactive status
- Trial availability
- Maximum users
- Maximum projects
- Maximum BOQs
- Maximum storage
- Maximum AI processing credits
- Maximum OCR pages
- Maximum translations
- Included features
- Included updates
- Feature-update eligibility
- Renewal settings
- Grace period
- Refund policy
- Display order

Plan types should include:

```text
One-Time
Monthly
Three-Month
Six-Month
Annual
Lifetime
```

Admin must be able to create, edit, activate, deactivate and archive plans.

Do not hard-code plan prices or durations.

---

# 4. One-Time Access

Support one-time purchases.

A one-time purchase may provide access for a configured period or for a defined product version.

Admin should be able to configure whether a one-time plan provides:

- Permanent access to the purchased version
- Access for a fixed number of days
- Access to selected modules only
- Access without future feature updates
- Access with optional paid updates

The system must clearly show the user:

```text
Access Type: One-Time
Access Expiry: [Date or Lifetime]
Included Version: [Version]
Future Updates: Not Included / Included
```

A one-time purchase must not automatically be treated as lifetime access unless the plan explicitly defines it as lifetime.

---

# 5. Monthly, Three-Month, Six-Month and Annual Subscriptions

Support recurring and fixed-term subscriptions.

Subscription durations:

- Monthly
- Three months
- Six months
- Annual

Each subscription should store:

- Start date
- End date
- Renewal date
- Payment status
- Subscription status
- Auto-renewal status
- Cancellation date
- Grace-period end date
- Current plan
- Previous plan
- Upgrade/downgrade history

Subscription statuses may include:

```text
Pending
Active
Trial
Past Due
Grace Period
Cancelled
Expired
Suspended
Refunded
```

If automatic recurring billing is supported by the payment provider, allow users to enable or disable auto-renewal.

If automatic renewal is not available, notify users before expiry and allow manual renewal.

---

# 6. Lifetime Access

Support lifetime plans.

A lifetime plan should provide access for the lifetime of the purchased product or organisation account according to the plan terms.

Lifetime access must clearly define whether it includes:

- Existing features
- Future minor updates
- Future major updates
- AI usage
- Storage
- New modules
- Mobile application access
- Support
- Feature-update top-ups

Example:

```text
Access Type: Lifetime
Core Platform Access: Included
Current Features: Included
Future Major Features: Require Update Top-Up
AI Usage: Subject to Usage Credits
```

Lifetime access must not automatically mean unlimited AI usage, unlimited storage or free access to every future feature unless explicitly configured by Admin.

---

# 7. Feature Entitlements

Create a feature-entitlement system.

Features should be managed independently from subscription plans.

Examples:

- Project management
- BOQ management
- Excel import
- PDF import
- OCR scanning
- AI BOQ extraction
- AI pricing
- Rate Library
- Supplier management
- Variations
- Interim payment certificates
- Actual cost tracking
- Advanced reports
- PDF export
- Excel export
- Flutter mobile access
- Offline mode
- Multilingual support
- Translation management
- Advanced analytics
- API access
- Team collaboration
- Document storage
- Feature updates

Each feature should have:

- Feature name
- Feature code
- Description
- Translations
- Module
- Version introduced
- Active/inactive status
- Included in plans
- Requires top-up status
- Usage limits
- Permission requirements

Use feature codes in application logic rather than hard-coded plan names.

Example:

```text
boq.import.excel
boq.import.pdf
ai.boq.extraction
ai.pricing.analysis
reports.advanced
updates.version_2_0
```

---

# 8. Core Features and New Feature Updates

Separate the platform into:

### Core Features

Features included in the user's purchased plan.

### New Feature Updates

Features released after the user's purchase or after the user's included update entitlement expires.

A user should be able to see:

```text
Included Features
Available Features
Locked Features
New Features Requiring Top-Up
```

When a new feature is released, Admin should be able to configure:

- Feature name
- Release version
- Release date
- Description
- Screenshots or documentation
- Required top-up amount
- Eligible plans
- Whether the feature is optional or mandatory
- Whether the feature is a major update or minor update
- Whether the feature is included for active subscribers
- Whether lifetime users must purchase it separately

Do not automatically charge users for new features.

Require the user to review and confirm the top-up purchase.

---

# 9. Feature Update Top-Ups

Create a top-up system for new features and updates.

A top-up may provide:

- Access to one new feature
- Access to a feature bundle
- Access to a major version
- Access to a new module
- Additional AI credits
- Additional OCR pages
- Additional storage
- Additional translation credits
- Additional users
- Additional projects
- Additional report exports

Each top-up product should support:

- Name
- Code
- Description
- Translations
- Price
- Currency
- Duration
- Permanent or time-limited access
- Included features
- Included usage credits
- Applicable plans
- Active/inactive status
- Release version
- Purchase limit
- Refund rules

Top-up types may include:

```text
Feature Unlock
Version Update
AI Credit Top-Up
OCR Credit Top-Up
Translation Credit Top-Up
Storage Top-Up
User Seat Top-Up
Project Limit Top-Up
Report Export Top-Up
```

---

# 10. Update Eligibility Rules

Admin must be able to configure update eligibility rules.

Examples:

- Monthly subscribers receive all updates while active.
- Annual subscribers receive updates during the active annual period.
- Lifetime users receive core updates but must top up for major new modules.
- One-time users retain access to the purchased version but must top up for later versions.
- Expired subscribers retain access only to features allowed by the plan.
- AI usage requires separate credits even when the user has platform access.

The system must calculate eligibility based on:

- User
- Organisation
- Subscription
- Plan
- Purchase date
- Current product version
- Feature release date
- Previous top-ups
- Feature entitlement
- Expiry date
- Payment status

---

# 11. Product Versions

Create product version management.

Store:

- Version number
- Version name
- Release date
- Release notes
- Major/minor/patch classification
- Included features
- Required top-up
- Eligible plans
- Minimum supported version
- Active status

Example:

```text
Version 1.0
Core BOQ Platform

Version 1.5
AI Pricing Improvements

Version 2.0
Advanced Project Cost Control

Version 3.0
Enterprise Procurement and Payment Certificates
```

Users should be able to view release notes and see which features require a top-up.

---

# 12. Subscription and Entitlement Dashboard

Create a user subscription dashboard showing:

- Current plan
- Access type
- Subscription status
- Start date
- Expiry date
- Renewal date
- Auto-renewal status
- Included features
- Locked features
- Available updates
- Purchased top-ups
- Remaining AI credits
- Remaining OCR credits
- Remaining translation credits
- Storage usage
- Project usage
- BOQ usage
- User-seat usage
- Payment history
- Invoices
- Upgrade options
- Renewal options

Example:

```text
Current Plan: Annual Professional
Status: Active
Expires: 31 December 2026
AI Credits Remaining: 420
OCR Pages Remaining: 180
New Updates Available: 3
Top-Up Required: Yes
```

All subscription labels and messages must be translated.

---

# 13. Organisation Subscriptions

Support both individual users and organisations.

An organisation should be able to purchase a plan and assign access to multiple users.

Store:

- Organisation
- Subscription
- Plan
- Billing contact
- Number of seats
- Used seats
- Available seats
- Project limits
- Storage limits
- AI limits
- Feature entitlements
- Top-ups
- Billing history

Organisation administrators should be able to:

- Invite users
- Remove users
- Assign roles
- Assign project access
- Manage seats
- View usage
- Purchase top-ups
- View invoices
- Manage renewals

Do not allow ordinary organisation users to change billing or purchase top-ups unless permission is granted.

---

# 14. Usage Limits

Plans and top-ups should support usage limits.

Possible limits include:

- Number of projects
- Number of BOQs
- Number of BOQ items
- Number of users
- Storage size
- AI requests
- AI tokens
- OCR pages
- Scanned documents
- Translations
- PDF exports
- Excel exports
- API requests
- Site photos
- Supplier quotations

When a user approaches a limit, show a warning.

Example:

```text
You have used 90% of your monthly AI credits.
Purchase an AI Credit Top-Up or upgrade your plan.
```

When a limit is reached, do not silently fail.

Show:

- Current usage
- Allowed usage
- Required top-up
- Upgrade option
- Contact/support option

---

# 15. AI and Credit Consumption

AI processing may consume credits depending on the plan.

Track usage for:

- BOQ extraction
- OCR
- AI pricing
- AI review
- AI translation
- AI cost optimisation
- Rate explanations
- Supplier quotation extraction

Before starting a large AI task, show an estimated credit cost where possible.

Example:

```text
Estimated usage:
OCR: 12 pages
AI extraction: 1 BOQ processing credit
Translation: 85 item credits
```

Require confirmation if the task will consume paid credits.

Store:

- User
- Organisation
- Feature
- Request type
- Credits consumed
- Date/time
- Project
- BOQ
- Processing status
- Provider
- Model
- Error details where applicable

Do not deduct credits for failed processing unless the provider successfully processed the request and the configured policy allows deduction.

---

# 16. Payment Gateway Architecture

Payment providers must be configurable.

Do not hard-code one payment provider.

Create a payment gateway abstraction supporting:

- Mobile money
- Bank transfer
- Card payments
- Online payment gateways
- Manual payment verification
- Organisation invoicing

Admin should be able to configure:

- Payment provider
- API credentials
- Currency
- Webhook URL
- Test/live mode
- Payment timeout
- Refund settings
- Supported countries
- Supported payment methods

All payment credentials must be encrypted.

Flutter must never receive private payment gateway credentials.

Payment flow:

```text
Web/Flutter
      ↓
Laravel Payment Service
      ↓
Configured Payment Gateway
      ↓
Webhook/Callback
      ↓
Laravel Verifies Payment
      ↓
Subscription or Top-Up Activated
```

---

# 17. Payments and Transactions

Create transaction records containing:

- Transaction reference
- User
- Organisation
- Product
- Plan
- Top-up
- Amount
- Currency
- Payment method
- Gateway
- Gateway transaction ID
- Status
- Initiated date
- Completed date
- Failed date
- Refund status
- Receipt
- Invoice
- Failure reason

Transaction statuses may include:

```text
Initiated
Pending
Successful
Failed
Cancelled
Refunded
Partially Refunded
Under Review
```

Never activate a subscription or top-up based only on a client-side success message.

Verify payment server-side using the payment gateway response or webhook.

---

# 18. Invoices and Receipts

Generate invoices and receipts for:

- New subscriptions
- Renewals
- One-time purchases
- Lifetime purchases
- Feature-update top-ups
- AI credit top-ups
- Storage top-ups
- Seat top-ups
- Refunds

Invoices should include:

- Organisation/user
- Billing address
- Invoice number
- Transaction reference
- Product
- Plan/top-up
- Amount
- Tax/VAT where applicable
- Currency
- Payment status
- Payment date
- Due date where applicable

Invoices and receipts must support:

- English
- Luganda
- Bilingual output
- PDF
- Print
- Email delivery

---

# 19. Subscription Lifecycle

Implement the following lifecycle:

```text
Plan Selected
      ↓
Payment Initiated
      ↓
Payment Verified
      ↓
Subscription Activated
      ↓
Entitlements Granted
      ↓
Usage Tracked
      ↓
Renewal Reminder
      ↓
Renewed / Expired / Cancelled
```

For top-ups:

```text
Top-Up Selected
      ↓
Payment Initiated
      ↓
Payment Verified
      ↓
Feature or Credits Activated
      ↓
Entitlement Expiry or Permanent Access
```

Handle:

- Failed payments
- Duplicate callbacks
- Delayed payments
- Refunds
- Chargebacks
- Expired subscriptions
- Grace periods
- Manual payment approval
- Subscription upgrades
- Subscription downgrades
- Plan changes
- Proration where supported

---

# 20. Subscription Expiry and Grace Period

Admin should configure:

- Expiry reminders
- Grace period duration
- Features available during grace period
- Read-only access after expiry
- Export access after expiry
- Data retention period
- Account suspension period
- Data deletion policy

Example:

```text
Subscription Expired
      ↓
Grace Period
      ↓
Read-Only Access
      ↓
Account Suspended
```

Do not delete project or BOQ data immediately after expiry.

Users should be able to renew or purchase an appropriate plan.

---

# 21. Access Control Based on Entitlements

Use both permissions and subscription entitlements.

A user must have:

```text
Permission
      AND
Valid Feature Entitlement
```

to access a paid feature.

Example:

```text
Can use AI Pricing =
User Permission
AND
Active Plan or AI Top-Up
AND
Available AI Credits
```

If access is denied, show a clear translated message:

```text
This feature is not included in your current plan.
Purchase an update top-up or upgrade your subscription.
```

Do not expose internal feature codes to ordinary users.

---

# 22. Subscription-Aware API

All protected API endpoints must verify:

- Authentication
- Organisation access
- User permission
- Subscription status
- Feature entitlement
- Usage limit
- Credit balance where applicable

Return structured API responses for entitlement failures.

Example:

```json
{
  "success": false,
  "error_code": "FEATURE_TOPUP_REQUIRED",
  "message": "This feature requires an update top-up.",
  "feature": "advanced_cost_control",
  "topup_options": []
}
```

The Flutter application should use this response to show an upgrade or top-up screen.

---

# 23. Subscription Notifications

Notify users about:

- Payment successful
- Payment failed
- Subscription activated
- Subscription expiring
- Subscription expired
- Renewal due
- Auto-renewal failed
- Grace period started
- Feature update available
- Top-up required
- Top-up successful
- AI credits running low
- Storage limit approaching
- Project limit approaching
- New version released
- Refund processed

Notifications must be sent in the recipient's preferred language.

Support:

- In-app notifications
- Email
- Flutter push notifications where configured
- SMS where configured

---

# 24. Subscription Reports for Admin

Admin reports should include:

- Active subscriptions
- Expired subscriptions
- Cancelled subscriptions
- Lifetime users
- One-time users
- Monthly revenue
- Annual revenue
- Top-up revenue
- Revenue by plan
- Revenue by country
- Revenue by currency
- Failed payments
- Refunds
- Churn
- Renewals
- Feature adoption
- Top-up conversion
- AI credit usage
- Storage usage
- Organisation usage
- Trial conversion

---

# 25. Multilingual Architecture

The platform must be designed as a multilingual system from the beginning rather than adding translations later.

Initially support:

- English
- Luganda

The architecture must allow additional languages to be added without changing the database structure or rewriting application logic.

Potential future languages may include:

- Swahili
- French
- Arabic
- Portuguese
- Runyankole
- Acholi
- Ateso
- Other organisation-specific languages

Support multilingual content for:

- Web interface
- Flutter interface
- Navigation
- Buttons
- Forms
- Validation messages
- Notifications
- Emails
- Reports
- PDF exports
- Excel export headings
- Approval workflows
- AI explanations
- Help text
- Empty states
- Error messages
- System settings
- Subscription plans
- Payment messages
- Invoices
- Receipts
- Top-up descriptions
- User-generated translated content where applicable

---

# 26. Language Selection

Provide language selection in both Laravel and Flutter.

Example:

```text
English | Luganda
```

The language selector should be available:

- During login where appropriate
- In the user profile
- In application settings
- In the main navigation or header
- During first-time setup
- During checkout where appropriate
- During report generation

Remember the user's selected language.

Language preference should be stored for:

- Individual users
- Organisation defaults
- Project defaults where required
- Guest or unauthenticated sessions where applicable
- Mobile device preferences
- Billing and invoice preferences where applicable

Use the following priority:

```text
User Language
      ↓
Project Language
      ↓
Organisation Default Language
      ↓
Application Default Language
```

The user should be able to override the project or organisation language for their own interface.

---

# 27. Language and Locale Settings

Each language should support:

- Language code
- Language name
- Native language name
- Text direction
- Date format
- Time format
- Number format
- Decimal separator
- Thousands separator
- Currency display format
- Active/inactive status
- Translation completion status

Examples:

```text
English
Code: en
Direction: LTR
```

```text
Luganda
Code: lg
Direction: LTR
```

The system should be prepared for right-to-left languages such as Arabic even if they are not enabled initially.

Do not assume that all languages use the same:

- Date format
- Number format
- Currency placement
- Pluralisation rules
- Sentence structure
- Text length
- Sorting rules

---

# 28. Translation File Structure

Use Laravel translation files such as:

```text
lang/en/
lang/lg/
```

Organise translations into logical files:

```text
lang/en/messages.php
lang/en/validation.php
lang/en/navigation.php
lang/en/projects.php
lang/en/boq.php
lang/en/pricing.php
lang/en/reports.php
lang/en/notifications.php
lang/en/approvals.php
lang/en/settings.php
lang/en/subscriptions.php
lang/en/payments.php
lang/en/topups.php
lang/en/invoices.php
```

Use corresponding Flutter localization files, for example:

```text
lib/l10n/app_en.arb
lib/l10n/app_lg.arb
```

Do not hard-code UI text throughout the application.

Use translation keys such as:

```text
dashboard.title
projects.create
boq.upload
pricing.explain_rate
approvals.pending
subscriptions.current_plan
subscriptions.expiry_date
topups.purchase_update
payments.payment_successful
```

Translation keys should remain stable even when the translated wording changes.

---

# 29. Translation Management

Create an Admin translation management area.

Admin or authorised translators should be able to:

- View translation keys
- View English text
- View Luganda text
- Add missing translations
- Edit translations
- Search translation keys
- Filter untranslated entries
- View translation completion percentage
- Export translations
- Import translations
- Publish translation updates
- Restore previous translation versions

Do not allow ordinary users to modify system translations unless they have permission.

Record:

- Translator
- Date/time
- Previous translation
- New translation
- Language
- Translation key
- Approval status

---

# 30. Translation Fallback

If a translation is missing in the selected language, use the configured fallback language.

Default fallback:

```text
Selected Language
      ↓
English
      ↓
Translation Key
```

Do not display raw translation keys to users unless no translation exists at all.

For example, do not display:

```text
boq.upload
```

Instead display a readable fallback such as:

```text
Upload BOQ
```

Log missing translations for Admin review.

---

# 31. Multilingual Database Content

System interface translations should use translation files.

However, database content that users may need to view in multiple languages should support multilingual fields where appropriate.

Examples include:

- Project type names
- Work categories
- Material categories
- Unit descriptions
- Help text
- Report titles
- Organisation-defined categories
- AI-generated explanations
- Knowledge-base content
- Subscription plan names
- Feature names
- Top-up product names
- Payment method descriptions

Use a suitable structure such as:

```text
name_translations = {
    "en": "Concrete Works",
    "lg": "Emirimu gy'Concrete"
}
```

or a dedicated translations table.

Do not duplicate the entire project or BOQ record for every language.

The original BOQ description must remain unchanged. Translations should be stored separately.

---

# 32. BOQ Technical Language Preservation

Construction and quantity-surveying terminology must be handled carefully.

The system must preserve:

- Original technical description
- Original item code
- Original unit
- Original quantity
- Original rate
- Original amount
- Original language
- Original document formatting where possible

Translations are for understanding and reporting only.

Never replace the original contractual BOQ description with an AI translation.

Store:

```text
Original Description
Original Language
Translated Description
Translation Language
Translation Provider
Translation Date
Translation Confidence
```

---

# 33. Multilingual AI Processing

AI should be able to process BOQs written in different languages where the configured AI provider supports them.

The AI should:

- Detect the source language
- Preserve the original text
- Extract BOQ fields
- Translate descriptions when requested
- Classify construction items
- Match equivalent technical descriptions
- Explain pricing in the user's selected language
- Return structured data independently of the display language

The internal data model should use stable field names in English or another consistent technical schema, while the user interface can display translated labels.

Example internal fields:

```text
description
unit
quantity
original_rate
approved_rate
amount
```

These fields may be displayed in English or Luganda depending on the user's language.

---

# 34. AI Translation

Add an optional:

**Translate Description**

function.

A user should be able to view a technical BOQ description in:

- English
- Luganda
- Any other enabled language

The user should be able to select:

```text
Translate From
Translate To
```

The system should support:

- Single-item translation
- Bulk translation
- Translation of selected items
- Translation of an entire BOQ
- Translation of AI explanations
- Translation of report descriptions
- Translation of subscription and feature descriptions where authorised

However, the original technical BOQ description must remain unchanged.

AI translation is for understanding only.

Show a notice where appropriate:

```text
This translation is provided for reference. The original BOQ description remains the authoritative contractual text.
```

---

# 35. Translation Review

AI-generated translations should support review.

For each translated description, store:

- Source text
- Source language
- Target language
- Translated text
- AI confidence
- Translation status
- Reviewed by
- Review date
- Reviewer comments

Statuses may include:

- Pending review
- Accepted
- Edited
- Rejected
- Needs retranslation

Low-confidence translations should require manual review.

---

# 36. Multilingual Reports

Reports should be generated in the user's selected language or a language selected during report generation.

Allow the user to choose:

- Report language
- Currency
- Date format
- Number format
- Whether to show original descriptions
- Whether to show translated descriptions
- Whether to show both languages

Example report options:

```text
Report Language: Luganda
BOQ Description: Original English + Luganda Translation
Currency: UGX
Date Format: DD/MM/YYYY
```

For contractual and professional reports, allow:

```text
Original Technical Description
Translated Description
```

to appear together.

Subscription invoices, receipts and top-up confirmations must also support the selected language.

---

# 37. Multilingual Excel Exports

Excel exports should support:

- Selected report language
- Original description
- Translated description
- Bilingual headings
- Currency labels
- Localised dates
- Localised number formatting
- Subscription and usage reports
- Payment and transaction reports

Allow export options such as:

```text
English only
Luganda only
English and Luganda
Original language plus selected translation
```

Do not translate item codes, units or numerical values unless explicitly requested.

---

# 38. Multilingual PDF Exports

PDF generation must support:

- English
- Luganda
- Future languages
- Unicode characters
- Appropriate fonts
- Correct line wrapping
- Correct table layout
- Long translated descriptions
- Bilingual reports
- Localised dates and numbers
- Subscription invoices
- Payment receipts
- Top-up receipts
- Release notes

Use fonts that support all enabled languages.

Do not use a font that displays English correctly but produces missing characters or boxes for Luganda or future languages.

---

# 39. Main Dashboard

Create a professional Civil Works dashboard containing:

- Total Projects
- Active Projects
- Completed Projects
- BOQs
- BOQs awaiting review
- BOQs analysed by AI
- Total estimated project value
- Approved project value
- Current expenditure
- Variations
- Pending approvals
- Recent uploads
- Recent AI analyses
- Current subscription plan
- Subscription expiry
- Available updates
- Remaining AI credits
- Usage warnings

All dashboard labels, tooltips, filters, chart labels and notifications must be translated.

Statistics cards should be clickable.

Include charts for:

- Project expenditure
- BOQ values
- Budget vs actual
- Cost by project
- Cost by work category
- Monthly expenditure
- Material cost changes
- Subscription usage
- AI credit consumption where authorised

Chart labels and legends must use the selected language.

---

# 40. Project Management

Allow users to create civil works projects.

Project information should include:

- Project name
- Project code/reference
- Client
- Contractor
- Consultant
- Quantity Surveyor
- Project Manager
- Site Engineer
- Funding organisation
- Country
- District
- Location/site
- Project type
- Start date
- Expected completion date
- Contract value
- Currency
- Description
- Original project language
- Preferred project report language
- Status
- Project documents

Project types can include:

- Building construction
- Renovation
- Roads
- Drainage
- Water systems
- Health facilities
- Schools
- Residential
- Commercial
- Electrical works
- Mechanical works
- Landscaping
- Other civil works

Admin should manage these categories and their translations.

---

# 41. BOQ Management

Each project can contain one or multiple BOQs.

A BOQ should support:

```text
Project
   └── BOQ
       ├── Facility
       │   ├── Bill
       │   │   ├── Element
       │   │   │   ├── Sub-element
       │   │   │   └── BOQ Items
       │   │   └── Bill Summary
       │   └── Facility Summary
       └── Grand Summary
```

Do not force all BOQs into a flat structure.

All hierarchy labels, summaries and navigation elements must be translatable.

---

# 42. BOQ Item Structure

At minimum each BOQ item should support:

- Item number/code
- Description
- Original language
- Translated descriptions
- Unit
- Quantity
- Original rate
- AI suggested rate
- Approved rate
- Amount
- Currency
- Work category
- Material category
- Facility
- Bill
- Element
- Sub-element
- Location
- Pricing source
- Pricing date
- AI confidence score
- Translation confidence score where applicable
- Notes
- Status

Amount should calculate automatically:

```text
Amount = Quantity × Approved Rate
```

If the approved rate is empty, optionally use the selected/current rate according to the workflow.

---

# 43. Excel BOQ Upload

Allow a user to upload:

- .xlsx
- .xls
- .csv

The system should intelligently analyse the spreadsheet.

It should recognise common headings such as:

```text
ITEM
DESCRIPTION
UNIT
QTY
QUANTITY
RATE
RATE (UGX)
AMOUNT
AMOUNT (UGX)
```

It should also recognise translated or alternative headings where configured.

For example, the system should support language-specific heading mappings such as:

```text
English: DESCRIPTION
Luganda: Translated description equivalent
```

Admin should be able to configure additional heading aliases for each supported language.

It should also recognise:

- Bills
- Elements
- Sub-elements
- Section headings
- Facility names
- Summary pages
- Grand totals

Do not assume that every spreadsheet has column headings on row 1.

The importer should scan the workbook and detect the actual BOQ structure.

---

# 44. Multi-Sheet Excel Import

The uploaded reference BOQ contains many worksheets.

Therefore, the system must support Excel files containing:

- Cover sheet
- Facility list
- General conditions
- Preliminaries
- Fly sheets
- BOQ item sheets
- Summary sheets
- Multiple facilities

When a multi-sheet BOQ is uploaded:

1. Read all worksheets.
2. Identify sheets containing BOQ items.
3. Identify summary sheets.
4. Identify facility sheets.
5. Detect formulas.
6. Preserve relationships.
7. Detect the language of headings and descriptions where possible.
8. Show an import preview in the user's selected language.
9. Allow the user to exclude irrelevant worksheets.
10. Import after user confirmation.
11. Check whether the import is allowed by the user's plan and usage limits.
12. Deduct applicable import, OCR or AI credits according to the configured policy.

---

# 45. PDF BOQ Upload

Allow users to upload PDF BOQs.

AI/OCR should extract:

- Item
- Description
- Unit
- Quantity
- Rate
- Amount
- Headings
- Elements
- Bills
- Totals
- Source language

For scanned PDFs use OCR automatically.

Before saving, show:

**AI Extraction Preview**

so the user can correct anything the AI/OCR interpreted incorrectly.

The preview interface must be translated into the user's selected language.

If the user has insufficient OCR or AI credits, show the required top-up or upgrade options before processing.

---

# 46. Scan BOQ Using Flutter

Flutter should allow the user to:

- Take a photo
- Scan a page
- Scan multiple pages
- Select images from gallery
- Upload PDF
- Upload Excel
- Select the document language where known
- Allow automatic language detection

For multi-page scanning:

```text
Scan Page 1
Scan Page 2
Scan Page 3
...
Finish Scan
```

Combine pages into one BOQ upload session.

AI/OCR should process all pages.

All scanning instructions, buttons, errors and progress messages must be translated.

Scanning must check the user's OCR and AI entitlements before processing.

---

# 47. AI BOQ Extraction

The AI should identify the construction context of each item.

Example:

```text
Description:
200mm thick bed of hand packed stone base,
well rolled and compacted.

Unit:
SM

Quantity:
11
```

AI should understand that this is a construction item and correctly classify it.

Possible categories:

- Site preparation
- Excavation
- Earthworks
- Concrete
- Reinforcement
- Masonry
- Roofing
- Doors
- Windows
- Finishes
- Plumbing
- Electrical
- Mechanical
- Drainage
- External works
- Water supply
- Solar
- Sanitary installations
- Preliminaries
- Labour
- Equipment
- Other

Category names must support translations.

AI extraction must respect plan limits and available credits.

---

# 48. AI-Powered Pricing

This is one of the main functions.

For each BOQ item, AI should assist the user in estimating the current rate.

However:

**AI must not simply invent construction prices.**

Pricing should be based on available pricing evidence.

The platform should maintain a **Construction Rate Library**.

AI explanations and pricing recommendations must be displayed in the user's selected language while preserving the original pricing evidence.

AI pricing access may be included in selected plans or may require AI pricing credits/top-ups.

---

# 49. Construction Rate Library

Create a central database containing current rates.

Fields should include:

- Item
- Description
- Translated descriptions
- Original language
- Category
- Unit
- Rate
- Currency
- Country
- District/region
- Supplier
- Source
- Effective date
- Expiry/review date
- Created by
- Verified by
- Verification status

Examples:

```text
Cement
50 kg bag
UGX 38,000
Kampala
Supplier A
Updated 02 Sep 2026
```

```text
12mm reinforcement bar
KG
UGX ...
Karamoja
Supplier B
Updated ...
```

Rate descriptions should be searchable across supported languages.

---

# 50. AI Pricing Method

When analysing an uploaded BOQ, AI should:

1. Understand the BOQ description.
2. Detect the source language.
3. Normalise the description.
4. Identify the unit.
5. Search the internal Rate Library.
6. Find similar historical BOQ items.
7. Consider project location.
8. Consider rate date.
9. Consider supplier quotations.
10. Consider labour/material/equipment components where available.
11. Produce a suggested rate.
12. Explain the recommendation in the user's selected language.
13. Check available AI pricing credits before processing.
14. Record the credits consumed.

Show:

```text
Existing Rate:      UGX 45,000
AI Suggested Rate:  UGX 51,500
Difference:         +UGX 6,500
Variance:           +14.44%
Confidence:         87%
```

The labels should be translated according to the selected language.

---

# 51. AI Price Explanation

Include an:

**Explain Rate**

button.

Example:

```text
Suggested rate: UGX 51,500 per SM

Based on:
• 3 recent supplier prices
• 4 previous BOQs
• Karamoja regional adjustment
• Rates updated within the previous 90 days

Confidence: 87%
```

The explanation should be available in English and Luganda where configured.

The user must be able to see why AI suggested a particular price.

If the feature requires a top-up, show the required top-up before processing.

---

# 52. Price Sources

Rates may come from:

- Previous BOQs
- Supplier quotations
- Uploaded price lists
- Procurement records
- Purchase orders
- Historical projects
- Approved rate libraries
- Government/reference schedules
- Manually entered market surveys
- Approved external pricing feeds

Every suggested rate should retain its source information.

Source descriptions and verification statuses must be displayed in the selected language where translations are available.

---

# 53. AI Must Not Automatically Approve Rates

AI recommendations are advisory.

Use the workflow:

```text
AI Suggested Rate
       ↓
Quantity Surveyor/Authorised User Review
       ↓
Approve / Adjust / Reject
       ↓
Approved BOQ Rate
```

Do not allow AI to silently overwrite the original contractual BOQ.

All workflow statuses, approval actions and review instructions must be translated.

---

# 54. Preserve Original BOQ

Every uploaded BOQ should retain:

### Original BOQ

and separately maintain:

### Working BOQ

Therefore users can always compare:

```text
Original Rate
Current Rate
AI Suggested Rate
Approved Rate
```

Never destroy the uploaded original data.

Preserve the original language and original wording of the uploaded BOQ.

---

# 55. Rate Comparison

Provide a comparison screen.

Columns:

```text
Item
Description
Unit
Qty
Original Rate
AI Rate
Approved Rate
Variance
Original Amount
Revised Amount
Difference
```

Allow the user to choose whether to display:

- Original description
- Translated description
- Both descriptions

Use indicators for significant changes.

Example:

```text
0–5%       Normal
5–15%      Review
15%+       High variance
```

Thresholds should be configurable by Admin.

---

# 56. Price History

Clicking an item should show price history.

Example:

```text
Cement 50kg

Jan 2026     UGX 34,000
Mar 2026     UGX 35,500
May 2026     UGX 36,000
Aug 2026     UGX 38,000
Sep 2026     UGX 38,500
```

Allow filtering by:

- Country
- Region
- District
- Supplier
- Date range
- Currency
- Language for descriptions

---

# 57. Location-Based Pricing

Construction prices vary by location.

The platform should allow pricing based on:

- Country
- Region
- District
- Project site

For Uganda include districts such as:

- Kampala
- Wakiso
- Mukono
- Gulu
- Lira
- Moroto
- Kotido
- Karenga
- Kaabong
- Soroti
- Mbale
- Mbarara
- Fort Portal
- etc.

Admin should manage locations rather than hard-coding them.

Location names should support translations where necessary.

---

# 58. Transportation Adjustment

Allow materials to have:

- Base price
- Transport cost
- Loading/offloading
- Distance
- Wastage
- Site delivery cost

Example:

```text
Material price        40,000
Transport              4,500
Loading/offloading       500
----------------------------
Delivered rate         45,000
```

Labels and explanations must be translated.

---

# 59. Rate Build-Up

Support detailed rate analysis.

Example:

```text
1 m³ concrete
```

Build-up:

```text
Cement              xxx
Sand                xxx
Aggregate           xxx
Water               xxx
Labour              xxx
Equipment            xxx
Transport            xxx
Wastage              xxx
Overheads             xxx
Profit                xxx
-------------------------
RATE                  xxx
```

The AI may propose a build-up, but users must be able to review and edit it.

Build-up descriptions should support multilingual display without changing the underlying calculation structure.

---

# 60. Labour Rates

Create a labour-rate library.

Examples:

- Mason
- Carpenter
- Steel fixer
- Plumber
- Electrician
- Painter
- Welder
- General labourer
- Machine operator
- Site engineer

Rates may be:

- Hourly
- Daily
- Weekly
- Per unit/output

Labour categories and descriptions must support translations.

---

# 61. Equipment Rates

Maintain equipment rates.

Examples:

- Excavator
- Compactor
- Concrete mixer
- Vibrator
- Crane
- Truck
- Generator
- Water bowser
- Grader

Include:

- Rate
- Fuel
- Operator
- Maintenance
- Transportation

Equipment names and descriptions should support multilingual display.

---

# 62. Supplier Management

Create supplier management.

Store:

- Supplier name
- Contact person
- Phone
- Email
- Location
- Materials/services supplied
- Currency
- Price lists
- Quotations
- Rating
- Active status
- Preferred communication language where applicable

Users should be able to upload supplier price lists.

AI should extract pricing from the uploaded supplier document.

Supplier records should support translated descriptions and notes where required.

---

# 63. Supplier Quotations

Allow:

- PDF quotations
- Excel quotations
- Scanned quotation
- Camera capture

AI should extract:

```text
Product
Unit
Quantity
Price
VAT
Supplier
Quotation date
Validity date
Source language
```

After review, approved quotation rates can update the Rate Library.

Quotation review screens and extracted fields must be translated.

---

# 64. Multi-Currency

Users must be able to select the project/BOQ currency.

Initial currencies should include:

- UGX – Uganda Shilling
- USD – US Dollar
- EUR – Euro
- GBP – British Pound
- KES – Kenya Shilling
- TZS – Tanzania Shilling
- RWF – Rwanda Franc

Admin should be able to add more currencies.

Currency names, symbols and formatting should support localization.

Subscription prices and top-up prices may be configured separately for different currencies and countries.

---

# 65. Exchange Rates

Store:

- Currency
- Exchange rate
- Base currency
- Source
- Effective date

Support automatic exchange-rate retrieval where an authorised exchange-rate provider is configured.

Also allow Admin to manually enter an official project exchange rate.

This is important because contractual projects may use an agreed exchange rate rather than today's market exchange rate.

Show clearly whether the calculation uses:

```text
Live Market Rate
Project Contract Rate
Manual Rate
```

These labels must be translated.

---

# 66. Currency Conversion

Allow the user to switch the display currency without modifying the underlying original BOQ values.

Example:

```text
UGX 500,000,000
```

may be displayed as:

```text
USD equivalent
EUR equivalent
KES equivalent
```

The original transaction/project currency must always remain stored.

Currency conversion explanations, warnings and labels must be translated.

---

# 67. Language Support

Support multiple languages throughout the platform.

Initially support:

- English
- Luganda

Add language selection to both Laravel and Flutter.

Example:

```text
English | Luganda
```

Remember the user's selected language.

The system must be designed so that adding a new language requires adding translation resources and configuration rather than rewriting application logic.

---

# 68. Luganda

Important system interfaces should have Luganda translations.

Examples:

```text
Dashboard
Projects
BOQ
Upload BOQ
Materials
Suppliers
Reports
Settings
Approvals
Subscriptions
Payments
Top-Ups
Invoices
```

Translate:

- Navigation
- Buttons
- Forms
- Validation
- Notifications
- Reports
- Approval statuses
- AI explanations
- Help text
- Error messages
- Subscription messages
- Payment messages
- Top-up messages

The translation architecture must allow additional languages later.

Use Laravel translation files:

```text
lang/en/
lang/lg/
```

and corresponding localization files in Flutter.

Do not hard-code UI text throughout the application.

---

# 69. AI Translation

Add an optional:

**Translate Description**

function.

A user should be able to view a technical BOQ description in:

- English
- Luganda
- Other enabled languages

However, the original technical BOQ description must remain unchanged.

AI translation is for understanding only.

Store translation metadata and allow authorised users to review or edit translations.

Translation processing may consume translation credits depending on the user's plan.

---

# 70. BOQ Summary

Generate automatic summaries by:

- Element
- Bill
- Facility
- Project

Example:

```text
Preliminaries                xxx
Substructure                 xxx
Superstructure               xxx
Roofing                      xxx
Doors & Windows              xxx
Finishes                     xxx
Plumbing                     xxx
Electrical                   xxx
External Works               xxx
-------------------------------
SUBTOTAL                     xxx
VAT                          xxx
Contingency                  xxx
-------------------------------
GRAND TOTAL                  xxx
```

Summary headings, category names, notes and totals must be available in the selected language.

---

# 71. Multiple Facilities

The reference BOQ includes multiple facilities.

Therefore a project should support structures such as:

```text
Project

├── Facility A
│   ├── Maternity Ward
│   ├── OPD
│   ├── Staff House
│   ├── Latrine
│   ├── Medical Waste Pit
│   └── Solar Water System
│
└── Facility B
    ├── Maternity Ward
    ├── OPD
    ├── Staff House
    ├── Latrine
    ├── Medical Waste Pit
    └── Solar Water System
```

Each facility should have its own subtotal and contribution to the project grand total.

Facility names and descriptions should support multilingual display.

---

# 72. AI BOQ Review

Add:

**Review BOQ with AI**

AI should identify possible issues such as:

- Missing rate
- Missing quantity
- Missing unit
- Duplicate item
- Unusually high rate
- Unusually low rate
- Quantity anomaly
- Mathematical inconsistency
- Incorrect amount
- Rate outside recent market range
- Duplicate descriptions
- Inconsistent units
- Possible omitted items

Display findings as recommendations, not automatic corrections.

AI findings should be displayed in the user's selected language while retaining the original technical values.

AI review may consume AI credits according to the user's plan.

---

# 73. AI Cost Optimisation

Provide:

**Cost Optimisation**

AI can suggest possible savings such as:

- Alternative materials
- Better supplier prices
- Bulk purchasing
- Different construction approaches
- Local sourcing
- Transport optimisation

It should never automatically change the BOQ.

Suggestions should be available in the user's selected language.

---

# 74. BOQ Versioning

Every major change should create a BOQ version.

Example:

```text
Version 1 – Original Upload
Version 2 – AI Pricing
Version 3 – QS Review
Version 4 – Approved BOQ
Version 5 – Variation No.1
```

Version names, change descriptions and comparison labels must be translated.

Users should be able to compare versions while preserving original technical descriptions.

---

# 75. Variations

Create Variation Order management.

Allow:

- Addition
- Omission
- Quantity change
- Rate change
- New item

Record:

- Reason
- Requested by
- Date
- Original value
- Variation value
- Revised contract value
- Approval status
- Supporting document
- Original language
- Translated reason where applicable

Access to advanced variation management may depend on the user's plan or update entitlement.

---

# 76. Interim Payment Certificates

Prepare the system for:

- Interim valuations
- Work completed
- Previous certification
- Current certification
- Retention
- Advance recovery
- VAT
- Other deductions
- Net payable

Certificates should support multilingual headings and notes.

This module may be released as a future feature requiring a version update top-up depending on the configured product plan.

---

# 77. Actual Cost Tracking

Allow users to compare:

```text
BOQ Budget
Committed Cost
Actual Cost
Certified Cost
Paid Amount
Balance
```

Labels, charts and reports must support the selected language.

This should eventually make the platform useful throughout the whole construction lifecycle rather than only during tender preparation.

Advanced cost-control features may be managed as separate feature entitlements or product updates.

---

# 78. Document Management

Each project should support documents including:

- BOQ
- Drawings
- Contracts
- Quotations
- Invoices
- Certificates
- Site reports
- Variation documents
- Photos
- Payment documentation
- Correspondence

Use folder/subfolder organisation similar to OneDrive.

Store document metadata such as:

- Document language
- Translated title
- Document type
- Date
- Uploaded by
- Version
- Confidentiality level
- Storage usage

Storage limits must be enforced according to the user's plan and purchased storage top-ups.

---

# 79. Project Photos

Flutter should allow users to take site photographs.

Store:

- Project
- Date
- User
- Description
- Original language
- Translated description where applicable
- Stage/activity
- Location where permission is available

Allow before/after comparisons.

Photo storage must count toward the applicable storage entitlement.

---

# 80. Roles

Create configurable roles such as:

- Super Admin
- Administrator
- Project Manager
- Quantity Surveyor
- Site Engineer
- Civil Engineer
- Procurement Officer
- Finance
- Contractor
- Consultant
- Client
- Viewer
- Translator
- Language Administrator
- Billing Administrator
- Subscription Manager

Use permissions instead of relying only on hard-coded role names.

Translation management permissions should be separate from general administration permissions.

Billing and subscription permissions must be separate from project permissions.

---

# 81. Approval Workflow

Support approval workflows.

Example:

```text
BOQ Uploaded
      ↓
AI Analysed
      ↓
QS Review
      ↓
Project Manager Review
      ↓
Approved
```

Admin should be able to configure approval stages.

Approval stages, actions, rejection reasons and notifications must be translated.

Subscription and top-up purchases may also require manual approval where manual payment methods are used.

---

# 82. Audit Trail

Record important activities:

- User
- Action
- Date/time
- Project
- BOQ
- Previous value
- New value
- IP/device where appropriate
- User language at the time of action
- Subscription or entitlement affected
- Payment or transaction reference where applicable

Examples:

```text
John changed Item A rate:
UGX 45,000 → UGX 48,500
```

```text
Mary approved BOQ Version 3.
```

```text
Admin activated Annual Professional subscription for Organisation A.
```

```text
John purchased Advanced Cost Control Update.
```

Audit records should preserve the original action data while allowing the interface to display the action in the viewer's selected language.

---

# 83. Reports

Generate reports for:

- Project BOQ
- BOQ Summary
- Facility Summary
- Bill Summary
- AI Pricing Analysis
- Rate Comparison
- Market Price Analysis
- Supplier Comparison
- Variation Report
- Budget vs Actual
- Project Cost Report
- Materials Report
- Labour Report
- Equipment Report
- Approval Report
- Audit Trail
- Translation status
- Multilingual content coverage
- Subscription status
- Payment history
- Top-up history
- Usage report
- AI credit consumption
- Storage usage
- Feature entitlement report

Export:

- PDF
- Excel
- CSV
- Print

Allow the user to select the report language before generating the report.

Subscription and payment reports must be restricted to authorised users.

---

# 84. Flutter Dashboard

Flutter should provide the main functions available on Laravel, optimised for mobile.

Include:

- Dashboard
- Projects
- BOQs
- Upload
- Scan
- AI Analysis
- Rate Library
- Materials
- Suppliers
- Quotations
- Approvals
- Variations
- Site Photos
- Reports
- Notifications
- Profile
- Language
- Currency
- Translation review where authorised
- Subscription
- Plan comparison
- Payment history
- Top-ups
- Usage and credits
- Feature updates

All Flutter screens must support runtime language switching where practical.

---

# 85. Flutter Offline Support

Where practical, allow users working on construction sites to:

- View downloaded projects
- View BOQs
- Enter site information
- Capture photos
- Scan documents
- Select language
- View cached translations
- View cached subscription status
- View cached feature entitlements

while offline.

Do not allow offline users to complete a payment or activate a subscription without server confirmation.

Synchronise when internet becomes available.

Never allow offline synchronisation to silently overwrite a newer server version.

Store language preferences locally and synchronise them with the user's account when online.

---

# 86. Notifications

Notify relevant users when:

- BOQ uploaded
- AI analysis completed
- BOQ requires review
- Rate needs approval
- Price variance exceeds threshold
- Supplier quotation uploaded
- BOQ approved
- Variation submitted
- Variation approved/rejected
- Project budget is approaching threshold
- Translation is ready
- Translation requires review
- A required translation is missing
- Subscription activated
- Subscription expiring
- Subscription expired
- Payment failed
- Renewal due
- New feature released
- Top-up required
- Top-up successful
- AI credits running low
- Storage limit approaching

Support:

- In-app notifications
- Email
- Flutter push notifications where configured
- SMS where configured

Notifications must be sent in the recipient's preferred language.

---

# 87. AI Confidence

Every AI extraction, pricing recommendation and translation should contain a confidence level.

Example:

```text
92% High Confidence
72% Medium Confidence
41% Low Confidence
```

Low-confidence records should automatically require manual review.

Confidence labels and review instructions must be translated.

---

# 88. AI Processing Status

For large BOQs show processing progress:

```text
Uploading
Reading document
Detecting language
Checking subscription entitlement
Checking available credits
Extracting BOQ
Matching items
Checking quantities
Checking rates
Translating content where requested
Calculating amounts
Generating recommendations
Updating usage
Completed
```

Do not leave the user with an unexplained loading spinner.

All processing stages must be translated.

---

# 89. Validation

Before approving a BOQ check for:

- Missing descriptions
- Missing units
- Missing quantity
- Missing rates
- Invalid numbers
- Negative amounts
- Duplicate item numbers
- Broken summaries
- Unreviewed AI items
- Mathematical inconsistencies
- Missing required translations where configured

Validation messages must be translated.

Before activating a subscription or top-up check for:

- Successful payment verification
- Correct product
- Correct amount
- Correct currency
- Duplicate transaction
- Expired payment session
- Existing entitlement
- Refund or chargeback status

---

# 90. Formula Handling

Excel BOQs commonly contain formulas.

When importing Excel:

- Read formulas.
- Preserve original formulas where useful.
- Calculate equivalent database values.
- Record the original formula where necessary.
- Do not blindly trust formula results.
- Recalculate totals using server-side logic.

The Laravel system database should become the authoritative working calculation engine after import.

Formula errors and import warnings must be displayed in the user's selected language.

---

# 91. Units of Measurement

Create a standard unit library.

Examples:

```text
ITEM
NO
NR
LM
M
SM
M²
CM
M³
KG
TON
L
LS
DAY
HR
```

Allow aliases.

For example:

```text
SM = m²
CM = m³
LM = linear metre
```

AI should normalise equivalent units while preserving the original unit from the BOQ.

Unit names and descriptions should support multilingual display.

---

# 92. Admin Settings

Admin should manage:

- Organisation
- Logo
- Countries
- Districts
- Currencies
- Exchange rates
- Languages
- Translation files
- Translation keys
- Units
- Material categories
- Work categories
- Labour categories
- Equipment
- Suppliers
- Rate libraries
- AI provider
- AI API key
- AI model
- OCR provider
- AI confidence thresholds
- BOQ variance thresholds
- Roles
- Permissions
- Approval workflows
- Notification settings
- Default language
- Fallback language
- Date formats
- Number formats
- Currency formats
- Report language settings
- Translation review settings
- Subscription plans
- Feature entitlements
- Product versions
- Update releases
- Top-up products
- Payment gateways
- Payment methods
- Tax/VAT settings
- Billing settings
- Refund settings
- Grace periods
- Usage limits
- AI credit rules
- OCR credit rules
- Translation credit rules
- Storage limits
- Organisation seat limits
- Subscription notifications

---

# 93. Security

Implement:

- Laravel authentication
- API token authentication
- CSRF
- Role/permission middleware
- Subscription entitlement middleware
- Secure file uploads
- File MIME validation
- Rate limiting
- Encrypted sensitive configuration
- Audit logging
- Secure AI API key storage
- User activity logging
- Secure translation management
- Access control for confidential documents
- Secure payment processing
- Webhook signature verification
- Idempotent payment callbacks
- Protection against duplicate subscription activation
- Protection against duplicate top-up activation
- Secure invoice and receipt access

AI API keys and payment gateway credentials must never be exposed to Flutter.

Flutter should call Laravel, and Laravel should communicate with the AI provider and payment provider.

Architecture:

```text
Flutter
   ↓
Laravel API
   ↓
AI Service / Payment Service
```

NOT:

```text
Flutter → AI Provider Directly
Flutter → Payment Provider With Private Credentials
```

---

# 94. AI Data Privacy

Provide settings controlling what data is sent to the AI provider.

Do not unnecessarily send:

- Personal information
- Authentication information
- Confidential project information
- Unnecessary translated copies
- Internal notes not required for the requested AI task

Send only information necessary to process the requested BOQ task.

Log AI processing operations appropriately without storing API secrets.

Where possible, allow the organisation to choose whether AI translation data may be retained by the AI provider.

---

# 95. Do Not Fabricate "Current Market Prices"

This requirement is critical.

The system should never present an AI-generated number as a verified current market rate merely because an AI model produced it.

Rates must distinguish between:

```text
Verified Rate
Supplier Rate
Historical Rate
Reference Rate
AI Estimate
```

Every price should show:

- Source
- Date
- Location
- Currency
- Verification status

Example:

```text
UGX 38,500

Source: ABC Hardware Quotation
Location: Kampala
Date: 03 September 2026
Status: Verified
```

This makes the platform suitable for professional quantity surveying and civil works.

Price-source labels and explanations must be available in the user's selected language.

---

# 96. AI Matching Existing BOQ Items

Create semantic matching.

If the uploaded BOQ contains:

```text
Excavate trenches for wall foundations commencing
from reduced levels not exceeding 1.5m deep.
```

and the Rate Library contains a similar item with slightly different wording, AI should identify the likely match.

Show:

```text
Matched Rate Library Item
Similarity: 94%
```

but require review where confidence is below the configured threshold.

Matching explanations should be displayed in the user's selected language.

AI matching may consume credits according to the user's plan.

---

# 97. Reusable Knowledge

When an authorised user approves a BOQ rate, allow that approved information to improve the organisation's internal pricing library.

Store:

```text
Description
Normalised description
Translated descriptions
Original language
Unit
Rate
Location
Project type
Date
Source BOQ
Approved by
```

This will make future BOQ pricing progressively more useful.

---

# 98. Search

Add powerful search across:

- Projects
- BOQs
- BOQ items
- Materials
- Suppliers
- Quotations
- Rate Library
- Translation keys
- Translated descriptions
- Subscription plans
- Feature updates
- Top-up products
- Payment transactions

A user should be able to search something such as:

```text
12mm reinforcement
```

in English or Luganda and see current and historical matching rates.

Search should support:

- Original descriptions
- Translated descriptions
- Synonyms
- Technical abbreviations
- Unit aliases
- Language filters
- Feature names
- Plan names
- Transaction references

---

# 99. UX Requirements

Use:

- Professional dashboards
- Stat cards
- Tabs
- Modal CRUD forms
- Search
- Filters
- Pagination
- Responsive layouts
- Font Awesome icons
- Custom confirmation popups
- Auto-fading success/error notifications
- Language selector
- Clear translation status indicators
- Bilingual display options where appropriate
- Subscription status indicators
- Usage progress bars
- Feature lock indicators
- Upgrade and top-up prompts
- Payment status indicators
- Invoice download buttons

Avoid default JavaScript:

```text
alert()
confirm()
```

Use customised application modals.

Ensure translated text can expand without breaking layouts.

Do not rely on fixed-width buttons or containers that only work for English text.

Subscription and top-up prompts must be clear but must not block unrelated features unnecessarily.

---

# 100. Laravel Pages Must Be Complete

Do not generate empty pages or placeholders.

For every module provide complete:

- Migration
- Model
- Controller
- Service
- Form Requests
- Routes
- Blade views
- JavaScript
- API endpoints where required
- Permissions
- Subscription entitlement checks
- Search
- Filters
- Pagination
- CRUD
- Validation
- Translation keys
- Language switching
- Localised validation messages
- Localised notifications
- Usage-limit checks
- Payment integration where applicable
- Invoice and receipt generation where applicable

---

# 101. Flutter Screens Must Be Complete

Flutter screens must integrate with the real Laravel API.

Do not use permanent mock data once the backend endpoint exists.

Include:

- Models
- API services/repositories
- State management
- Screens
- Forms
- Validation
- Loading state
- Empty state
- Error state
- Pagination
- Authentication handling
- Localization
- Language switching
- Cached translations where appropriate
- Localised error messages
- Localised notifications
- Subscription status
- Feature entitlement status
- Usage and credit balances
- Plan comparison
- Top-up purchase flow
- Payment status handling
- Invoice and receipt access

---

# 102. Separate Laravel and Flutter Development

Develop and document the two components separately:

```text
civil-works-web/
```

for Laravel, and:

```text
civil_works_mobile/
```

for Flutter.

Do not mix Flutter source code into Laravel source directories.

Both should communicate through the Laravel API.

---

# 103. Initial Development Sequence

Do not attempt to implement everything blindly at once.

## Phase 1 – Foundation

Create:

- Laravel application
- MySQL database
- Authentication
- Roles and permissions
- API authentication
- Base UI
- Admin settings
- Language configuration
- Translation file structure
- English translations
- Luganda translations
- Flutter application foundation
- Flutter localization foundation
- Unicode-compatible fonts
- Language selection and persistence
- Subscription architecture
- Feature-entitlement architecture
- Product-version architecture
- Payment gateway abstraction
- Transaction architecture
- Invoice and receipt architecture

## Phase 2 – Subscription and Billing

Create:

- Subscription plans
- One-time plans
- Monthly plans
- Three-month plans
- Six-month plans
- Annual plans
- Lifetime plans
- User subscriptions
- Organisation subscriptions
- Feature entitlements
- Product versions
- Update releases
- Top-up products
- AI/OCR/translation credits
- Usage tracking
- Payment transactions
- Payment callbacks/webhooks
- Invoices
- Receipts
- Subscription notifications
- Expiry and grace-period handling

Test all subscription types before building paid modules.

## Phase 3 – Projects

Create complete:

- Project management
- Project users
- Facilities
- Documents
- Project language settings
- Multilingual project categories
- Subscription and project-limit enforcement

## Phase 4 – BOQ Engine

Create:

- BOQs
- Bills
- Elements
- BOQ items
- Summaries
- Excel importer
- Formula handling
- Language detection
- Translation metadata
- Usage and entitlement checks

Test using the supplied:

**Lot - 8 - BoQ.xlsx**

The system should be capable of importing its multi-sheet structure correctly.

## Phase 5 – AI/OCR

Implement:

- PDF extraction
- Scanning
- Image OCR
- AI structure detection
- AI item classification
- AI validation
- Language detection
- AI translation
- Translation review
- AI credit tracking
- OCR credit tracking
- Top-up prompts when credits are insufficient

## Phase 6 – Pricing Engine

Implement:

- Rate Library
- Materials
- Labour
- Equipment
- Suppliers
- Quotations
- Price history
- Regional prices
- Rate build-ups
- Multilingual rate descriptions

## Phase 7 – AI Pricing

Implement:

- Semantic matching
- Suggested rates
- Confidence scores
- Explanations
- Variance analysis
- Approval workflow
- Multilingual AI explanations
- AI credit consumption
- Pricing feature entitlement checks

## Phase 8 – Project Cost Control

Implement:

- Variations
- Actual costs
- Budget vs actual
- Interim valuations
- Payment certificates
- Multilingual reports
- Feature-update entitlement checks
- Top-up prompts for locked modules

## Phase 9 – Flutter

Complete corresponding Flutter functionality using the Laravel API.

Implement:

- Language switching
- Offline language preferences
- Multilingual forms
- Multilingual notifications
- Translation-aware document and BOQ views
- Subscription dashboard
- Plan selection
- Top-up purchase flow
- Payment status
- Usage and credit display
- Feature update display

## Phase 10 – Reporting

Complete:

- Excel
- PDF
- CSV
- Print
- Dashboards
- English reports
- Luganda reports
- Bilingual reports
- Localised dates, numbers and currencies
- Subscription reports
- Payment reports
- Top-up reports
- Usage reports
- Invoice and receipt exports

## Phase 11 – QA

Test:

- Excel uploads
- Large multi-sheet BOQs
- PDFs
- Scanned BOQs
- AI extraction
- Calculations
- Currency conversions
- English
- Luganda
- Missing translation fallback
- Translation switching
- Long translated text
- Unicode characters
- PDF font rendering
- Excel multilingual exports
- One-time purchases
- Monthly subscriptions
- Three-month subscriptions
- Six-month subscriptions
- Annual subscriptions
- Lifetime purchases
- Subscription expiry
- Grace periods
- Renewals
- Failed payments
- Refunds
- Duplicate payment callbacks
- Feature update top-ups
- AI credit deductions
- OCR credit deductions
- Translation credit deductions
- Usage limits
- Permissions
- Approval workflows
- Laravel
- Flutter
- Offline synchronisation

---

# 104. Initial Test Using Supplied BOQ

Use **Lot - 8 - BoQ.xlsx** as the first real import test.

The importer should identify structures comparable to:

```text
COVER
LIST OF FACILITIES
GENERAL CONDITIONS / PRELIMINARIES

FACILITY
    ↓
MATERNITY WARD
OPD BLOCK
STAFF HOUSE
LATRINES
PLACENTA PIT
MEDICAL WASTE PIT
SOLAR WATER SYSTEM
```

and line-item structures comparable to:

```text
ITEM
DESCRIPTION
UNIT
QTY
RATE (UGX)
AMOUNT (UGX)
```

It must also recognise summary worksheets and avoid importing summary totals as duplicate physical work items.

The import preview should allow the user to view:

- Original language
- Detected language
- English translation where available
- Luganda translation where available
- Original headings
- Normalised headings
- Import warnings
- Estimated AI/OCR credit usage
- Required plan or top-up where applicable

---

# 105. Final Goal

The final system should enable a user to:

```text
Create Account
      ↓
Select One-Time, Monthly, 3-Month, 6-Month, Annual or Lifetime Plan
      ↓
Complete Payment
      ↓
Receive Subscription and Feature Entitlements
      ↓
Select Currency & Language
      ↓
Create Project
      ↓
Upload Excel / PDF
or
Scan BOQ with Phone
      ↓
AI Detects Document Language
      ↓
AI Checks Available Credits
      ↓
AI Extracts BOQ
      ↓
User Reviews Extraction
      ↓
Translate Descriptions if Required
      ↓
System Matches Current Rate Library
      ↓
AI Suggests Rates
      ↓
User Reviews Price Sources
      ↓
QS/Authorised User Approves Rates
      ↓
BOQ Automatically Recalculates
      ↓
Generate Summary in Selected Language
      ↓
Compare Original vs Current Cost
      ↓
Approve BOQ
      ↓
Export English, Luganda or Bilingual PDF / Excel
      ↓
Receive Notifications About New Features
      ↓
Purchase Update Top-Up When Required
      ↓
Unlock New Features or Product Versions
      ↓
Continue Monitoring Civil Works Costs
```

The goal is not simply an Excel reader.

It should become a **complete multilingual Civil Works BOQ, Quantity Surveying, AI Cost Estimation, Subscription, Feature Update and Project Cost Control platform** for both web and mobile.

The platform must preserve original technical and contractual content while allowing users to work comfortably in English, Luganda and additional languages added in the future.

The commercial model must support:

- One-time purchases
- Monthly subscriptions
- Three-month subscriptions
- Six-month subscriptions
- Annual subscriptions
- Lifetime access
- Paid AI/OCR/translation credits where configured
- Paid feature-update top-ups
- Paid major-version updates
- Organisation subscriptions
- Usage-based limits
- Secure payments
- Invoices and receipts
- Subscription renewals
- Grace periods
- Refunds
- Feature entitlement management

Users must clearly understand what their plan includes, what expires, what remains available after expiry and which new features require a top-up.

# Federation Manager — UI Manual Test Sheet

**Application:** Federation Manager — Laravel 13 SAML2 federation registry  
**Base URL:** http://81.180.84.177:8092  
**Date:** 2026-05-04  
**Tester:** _______________  
**Build/Commit:** _______________  

---

## Test Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@example.com | password123 |
| Operator | operator@example.com | password123 |

---

## Test Data Reference

- 1 Federation: "Default Federation" (status: active)
- 12 entities: 5 IdP + 5 SP (healthy certs, active, in Default Federation) + 1 IdP + 1 SP (certs expiring in 10 days)
- 12 certificates: 10 healthy, 2 critical (10-day expiry)

---

## Section Index

1. [Authentication (TC-001 – TC-010)](#1-authentication)
2. [Dashboard (TC-011 – TC-015)](#2-dashboard)
3. [Entity List & Filtering (TC-016 – TC-030)](#3-entity-list--filtering)
4. [Entity Create (TC-031 – TC-050)](#4-entity-create)
5. [Entity Edit (TC-051 – TC-065)](#5-entity-edit)
6. [Entity Lifecycle — Suspend (TC-066 – TC-073)](#6-entity-lifecycle--suspend)
7. [Entity Lifecycle — Reactivate (TC-074 – TC-078)](#7-entity-lifecycle--reactivate)
8. [Entity Lifecycle — Delete / Restore (TC-079 – TC-086)](#8-entity-lifecycle--delete--restore)
9. [Entity Metadata & Validation (TC-087 – TC-092)](#9-entity-metadata--validation)
10. [Entity Import (TC-093 – TC-099)](#10-entity-import)
11. [Federation List (TC-100 – TC-105)](#11-federation-list)
12. [Federation Create (TC-106 – TC-111)](#12-federation-create)
13. [Federation Show — General Tab (TC-112 – TC-118)](#13-federation-show--general-tab)
14. [Federation Show — Membership Tab (TC-119 – TC-131)](#14-federation-show--membership-tab)
15. [Federation Show — Signing Keys Tab (TC-200 – TC-208)](#15-federation-show--signing-keys-tab)
16. [Federation Show — Metadata Tab (TC-132 – TC-137)](#16-federation-show--metadata-tab)
17. [Federation Show — Attributes Tab (TC-138 – TC-141)](#17-federation-show--attributes-tab)
18. [Federation Show — Validators Tab (TC-142 – TC-145)](#18-federation-show--validators-tab)
19. [Federation Lifecycle — Deactivate (TC-146 – TC-150)](#19-federation-lifecycle--deactivate)
20. [Federation Lifecycle — Delete / Restore (TC-151 – TC-155)](#20-federation-lifecycle--delete--restore)
21. [Certificates Monitor (TC-155 – TC-161)](#21-certificates-monitor)
22. [Metadata Page (TC-162 – TC-165)](#22-metadata-page)
23. [Users (TC-166 – TC-172)](#23-users)
24. [Audit Log (TC-173 – TC-176)](#24-audit-log)
25. [Attributes (TC-177 – TC-180)](#25-attributes)
26. [Mail Templates (TC-181 – TC-184)](#26-mail-templates)
27. [Compliance Rules (TC-185 – TC-187)](#27-compliance-rules)
28. [Scheduler (TC-188 – TC-190)](#28-scheduler)
29. [Preferences (TC-191 – TC-193)](#29-preferences)
30. [Role-Based Access — Guest (TC-194 – TC-195)](#30-role-based-access--guest)

---

## 1. Authentication

### [TC-001] Successful login as Admin
**URL:** http://81.180.84.177:8092/login  
**Prerequisites:** Not logged in; browser cookies cleared  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/login  
2. Enter `admin@example.com` in the Email field  
3. Enter `password123` in the Password field  
4. Click the "Login" button  
**Expected result:** Redirected to http://81.180.84.177:8092/dashboard; sidebar shows all menu items including the Admin section divider and items (Mail Templates, Attributes, Compliance Rules, Scheduler, Preferences, Users, Audit Log)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-002] Successful login as Operator
**URL:** http://81.180.84.177:8092/login  
**Prerequisites:** Not logged in  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/login  
2. Enter `operator@example.com` in the Email field  
3. Enter `password123` in the Password field  
4. Click the "Login" button  
**Expected result:** Redirected to /dashboard; sidebar shows Dashboard, Entities, Federations, Certificates, Metadata, Mail Templates, Attributes, Compliance Rules, Scheduler, Preferences, Users, Audit Log  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-003] Login with wrong password
**URL:** http://81.180.84.177:8092/login  
**Prerequisites:** Not logged in  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/login  
2. Enter `admin@example.com` in the Email field  
3. Enter `wrongpassword` in the Password field  
4. Click the "Login" button  
**Expected result:** Page reloads showing a validation error message (e.g., "These credentials do not match our records."); user remains on /login  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-004] Login with empty fields
**URL:** http://81.180.84.177:8092/login  
**Prerequisites:** Not logged in  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/login  
2. Leave both Email and Password fields empty  
3. Click the "Login" button  
**Expected result:** HTML5 browser validation or server-side validation prevents submission; validation messages shown for required fields; user stays on /login  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-005] Login with invalid email format
**URL:** http://81.180.84.177:8092/login  
**Prerequisites:** Not logged in  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/login  
2. Enter `notanemail` in the Email field  
3. Enter `password123` in the Password field  
4. Click the "Login" button  
**Expected result:** Validation error shown indicating the email format is invalid; user stays on /login  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-006] Logout
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Click the user avatar or user menu in the top-right topbar  
2. Click the "Logout" option  
**Expected result:** POST /logout is triggered; user is redirected to /login; accessing /dashboard redirects back to /login  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-007] Redirect unauthenticated user to login
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Not logged in  
**Steps:**  
1. Ensure no active session (clear cookies or use incognito)  
2. Navigate directly to http://81.180.84.177:8092/entities  
**Expected result:** Browser redirects to http://81.180.84.177:8092/login; the /entities page is not shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-008] Language switcher — switch to Romanian
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com; current language is English  
**Steps:**  
1. Locate the language switcher dropdown in the topbar (shows current language flag/label)  
2. Click it to open the dropdown  
3. Click "RO" or the Romanian option  
**Expected result:** Page reloads or updates; UI labels switch to Romanian (e.g., "Tablou de bord" or equivalent); the language indicator in topbar shows RO  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-009] Language switcher — switch back to English
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com; current language is Romanian (from TC-008)  
**Steps:**  
1. Locate the language switcher dropdown in the topbar  
2. Click it to open the dropdown  
3. Click "EN" or the English option  
**Expected result:** UI labels revert to English (e.g., "Dashboard" appears in sidebar); language indicator shows EN  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-010] SAML login link present on login page
**URL:** http://81.180.84.177:8092/login  
**Prerequisites:** Not logged in  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/login  
2. Inspect the login page for a SAML/SSO login link or button  
**Expected result:** A link or button referencing /saml/login is visible on the login page  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 2. Dashboard

### [TC-011] Dashboard loads for Admin
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/dashboard  
**Expected result:** Dashboard page loads without errors; shows summary statistics or widgets; page title contains "Dashboard"  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-012] Dashboard entity count reflects seeded data
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com; development seeder run  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/dashboard  
2. Locate the entities summary widget or count  
**Expected result:** Entity count shows 12 total entities (or reflects the seeded count of 10 active + 2 critical); no 500 error  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-013] Dashboard federation count reflects seeded data
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com; development seeder run  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/dashboard  
2. Locate the federations summary widget or count  
**Expected result:** Federation count shows 1 (Default Federation); no 500 error  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-014] Dashboard certificate warning indicator
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com; 2 entities have certs expiring in 10 days  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/dashboard  
2. Look for a certificate warning or critical alert indicator  
**Expected result:** A visual indicator (badge, alert, or widget) is present showing 2 critical certificate(s) or a warning about expiring certificates  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-015] Dashboard sidebar navigation links work
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/dashboard  
2. Click "Entities" in the sidebar  
3. Click back/navigate to Dashboard  
4. Click "Federations" in the sidebar  
5. Click back/navigate to Dashboard  
6. Click "Certificates" in the sidebar  
**Expected result:** Each click navigates to the correct page (/entities, /federations, /certificates/monitor) without 404 or 500 errors  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 3. Entity List & Filtering

### [TC-016] Entity list loads
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
**Expected result:** Entity list page loads; shows a table or list of entities; 12 entities are visible (or paginated); stats bar visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-017] Entity list stats bar shows correct counts
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; seeder run  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Observe the stats bar above the entity list  
**Expected result:** Stats bar shows total entities (12), IdP count (6), SP count (6), and status breakdown (active count matches seeded data)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-018] Filter entities by type — IdP
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Locate the type filter (dropdown or tab labeled "IdP" or "Identity Providers")  
3. Select "IdP" filter  
**Expected result:** List updates in real-time (Livewire, no full page reload) to show only IdP entities; count shown = 6  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-019] Filter entities by type — SP
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Locate the type filter  
3. Select "SP" filter  
**Expected result:** List updates to show only SP entities; count shown = 6  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-020] Search entities by name
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Locate the search/filter text input  
3. Type a partial name of a known entity (e.g., the first few characters of a seeded entity name)  
**Expected result:** List filters in real-time (Livewire) to show only entities matching the search term; non-matching entities disappear  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-021] Search returns no results
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. In the search input, type `zzzznotexist`  
**Expected result:** The entity list shows zero results; an empty state message is displayed (e.g., "No entities found" or similar)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-022] Clear search restores full list
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; search term entered  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Type `zzzznotexist` in the search input  
3. Clear the search input (select all and delete)  
**Expected result:** Full list of 12 entities is restored without page reload  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-023] Filter entities by status — Active
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Locate the status filter dropdown  
3. Select "Active"  
**Expected result:** List shows only entities with status "active"; count matches seeded active entities  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-024] Filter entities by status — Draft
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Locate the status filter dropdown  
3. Select "Draft"  
**Expected result:** List filters to show only draft entities; if none exist in seeded data, empty state shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-025] Expandable row — expand entity details
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Click on an expand toggle (chevron or row) for any entity in the list  
**Expected result:** A details panel expands below the row showing additional entity information (entityID, type, federation memberships, cert status, etc.); no page reload  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-026] Collapse expanded entity row
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; one row expanded  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Expand an entity row (as in TC-025)  
3. Click the expand toggle again  
**Expected result:** The details panel collapses; the row returns to its compact state  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-027] Entity with critical certificate shows warning badge
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; 2 entities have certs expiring in 10 days  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Scan the list for entities with critical certificate status  
**Expected result:** Two entities display a visual warning/badge (e.g., red badge, "critical" label) indicating certificate expiry within 10 days  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-028] "Create entity" button navigates to create form
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Click the "Create" or "New Entity" button  
**Expected result:** Browser navigates to http://81.180.84.177:8092/entities/create  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-029] Click entity name navigates to edit page
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Click the name/link of any entity in the list  
**Expected result:** Browser navigates to http://81.180.84.177:8092/entities/{id}/edit for that entity  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-030] Sidebar Entities sub-links — IdPs and SPs
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. In the sidebar, click "Entities" to expand it (if collapsible)  
2. Click the "IdPs" sub-link  
3. Observe the entity list  
4. Click the "SPs" sub-link  
**Expected result:** Clicking "IdPs" filters the entity list to show only Identity Providers; clicking "SPs" filters to show only Service Providers  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 4. Entity Create

### [TC-031] Entity create page loads all tabs
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
**Expected result:** Page loads with 6 tabs visible: General, Endpoints, Certificates, UI Info, REFEDS, Languages  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-032] Create entity — General tab required fields validation
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Leave all fields empty on the General tab  
3. Click "Save" or "Submit"  
**Expected result:** Validation errors appear for required fields (entityID is required, type is required); entity is not created  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-033] Create IdP entity — General tab
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. On the General tab, enter entityID: `https://test-idp.example.com/idp`  
3. Select Type: "IdP"  
4. Set Status: "draft"  
5. Enter Registration Authority: `https://www.example.com`  
6. Enter Scope: `example.com`  
**Expected result:** All fields accept the values; no validation errors appear; Scope field is visible only for IdP type  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-034] Create entity — Scope field hidden for SP type
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. On the General tab, select Type: "SP"  
3. Observe the form fields  
**Expected result:** The Scope field is hidden or absent when entity type is SP  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-035] Create entity — switch to Endpoints tab
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Click the "Endpoints" tab  
**Expected result:** Endpoints tab content is displayed; for IdP type shows SSO and SLO fields; for SP type shows ACS and SLO fields  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-036] Create entity — add SSO endpoint (IdP)
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com; type set to IdP  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Select type IdP on General tab  
3. Switch to Endpoints tab  
4. Enter an SSO URL (e.g., `https://test-idp.example.com/sso`)  
5. Select a binding (e.g., HTTP-Redirect)  
**Expected result:** The SSO endpoint field accepts the URL and binding selection; no errors shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-037] Create entity — Certificates tab — add certificate
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Switch to the "Certificates" tab  
3. Click "Add Certificate" or the equivalent button  
4. Paste a valid X.509 certificate PEM string into the text area  
5. Select certificate use (signing or encryption)  
**Expected result:** Certificate is added to the list; it shows in the certificate table with the use label  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-038] Create entity — Certificates tab — remove certificate
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com; one certificate added (from TC-037)  
**Steps:**  
1. On the Certificates tab with at least one certificate added  
2. Click the remove/delete icon next to the certificate  
**Expected result:** Certificate is removed from the list immediately (Livewire update, no full reload)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-039] Create entity — UI Info tab — fill display name
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Switch to the "UI Info" tab  
3. Enter Display Name: `Test IdP Display Name`  
4. Enter Description: `A test identity provider`  
5. Enter Privacy URL: `https://test-idp.example.com/privacy`  
**Expected result:** All UI Info fields accept input without errors  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-040] Create entity — REFEDS tab — select entity categories
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Switch to the "REFEDS" tab  
3. Check "R&S" (Research & Scholarship) checkbox  
4. Check "SIRTFI" checkbox  
**Expected result:** Both checkboxes are selected; no errors; other REFEDS options (CoCo v2, HFD, Anonymous, Pseudonymous, Personalized, MFA) are also visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-041] Create entity — Languages tab — add multilingual name
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Switch to the "Languages" tab  
3. Add a display name in a second language (e.g., Romanian)  
4. Enter value: `Furnizor de Identitate Test`  
**Expected result:** Language tab accepts multilingual display name entries; the new language entry appears in the list  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-042] Save new IdP entity successfully
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Enter entityID: `https://new-idp-test.example.com/idp`  
3. Select Type: "IdP"  
4. Set Status: "draft"  
5. Enter Registration Authority: `https://www.example.com`  
6. Click "Save"  
**Expected result:** Entity is saved; a success toast message "Entity saved." appears top-right; user is redirected to the entity edit page (http://81.180.84.177:8092/entities/{new_id}/edit)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-043] Save new SP entity successfully
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Enter entityID: `https://new-sp-test.example.com/sp`  
3. Select Type: "SP"  
4. Set Status: "draft"  
5. Enter Registration Authority: `https://www.example.com`  
6. Click "Save"  
**Expected result:** Entity is saved; success toast "Entity saved." appears; redirected to the new entity's edit page  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-044] Duplicate entityID validation
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com; entity with entityID `https://new-idp-test.example.com/idp` already exists (from TC-042)  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Enter entityID: `https://new-idp-test.example.com/idp` (duplicate)  
3. Select Type: "IdP"  
4. Click "Save"  
**Expected result:** Validation error shown indicating the entityID already exists; entity is not created  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-045] EntityID must be a valid URI
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Enter entityID: `not a valid uri!!`  
3. Select Type: "IdP"  
4. Click "Save"  
**Expected result:** Validation error shown indicating entityID must be a valid URI/URL; entity is not created  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-046] Create entity — status set to pending
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Enter a unique entityID  
3. Select Type: "IdP"  
4. Set Status: "pending"  
5. Click "Save"  
**Expected result:** Entity is created with status "pending"; success toast "Entity saved." appears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-047] Create entity — loading spinner appears during save
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Fill in valid entityID and type  
3. Click "Save" and immediately observe the button/page  
**Expected result:** A brief loading spinner appears (wire:loading.delay.shortest behavior) while the Livewire save request is in progress, then disappears on completion  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-048] Create entity — ACS endpoint for SP type
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Select Type: "SP" on General tab  
3. Switch to Endpoints tab  
4. Enter an ACS URL (e.g., `https://new-sp-test.example.com/acs`)  
5. Select binding: HTTP-POST  
6. Set index: 1  
**Expected result:** ACS endpoint is accepted; SSO fields (specific to IdP) are not shown; no validation errors  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-049] Create entity — assurance profile selection on REFEDS tab
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Switch to REFEDS tab  
3. Locate assurance profiles section  
4. Select an assurance profile option  
**Expected result:** Assurance profile selection is saved/shown without errors  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-050] Create entity — logo URL on UI Info tab
**URL:** http://81.180.84.177:8092/entities/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/create  
2. Switch to UI Info tab  
3. Enter a logo URL: `https://test-idp.example.com/logo.png`  
4. Enter display name and save the entity  
**Expected result:** Logo URL is saved; on edit page the logo URL field shows `https://test-idp.example.com/logo.png`  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 5. Entity Edit

### [TC-051] Entity edit page loads for existing entity
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; at least one entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Click the name of any active entity  
**Expected result:** Edit page loads at /entities/{id}/edit with all 6 tabs; current entity data is pre-populated in the form fields  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-052] Edit entity — change display name and save
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. Switch to UI Info tab  
3. Change the Display Name to `Updated Display Name`  
4. Click "Save"  
**Expected result:** Success toast "Entity saved." appears; display name is updated; refreshing the page shows `Updated Display Name` in the UI Info tab  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-053] Edit entity — change registration authority and save
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. On General tab, update Registration Authority to `https://updated.example.com`  
3. Click "Save"  
**Expected result:** Success toast "Entity saved." appears; refreshing the page shows the updated registration authority  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-054] Edit entity — clear required field shows validation error
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. On General tab, clear the entityID field  
3. Click "Save"  
**Expected result:** Validation error shown for entityID (required field); entity is not saved  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-055] Edit entity — ARP (Attribute Release Policy) link
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. Look for an ARP link or tab (or navigate to /entities/{id}/arp)  
**Expected result:** ARP page or section is accessible; shows the entity's attribute release policy  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-056] Entity requested attributes page loads
**URL:** http://81.180.84.177:8092/entities/{id}/requested-attributes  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/{id}/requested-attributes  
**Expected result:** Requested attributes page loads without errors; shows attributes associated with the entity  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-057] Entity rules page loads
**URL:** http://81.180.84.177:8092/entities/{id}/rules  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/{id}/rules  
**Expected result:** Entity rules page loads without errors; shows compliance rules applied to the entity  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-058] Edit entity — add SLO endpoint
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. Switch to Endpoints tab  
3. Add a Single Logout (SLO) URL: `https://entity.example.com/slo`  
4. Select binding: HTTP-Redirect  
5. Click "Save"  
**Expected result:** Success toast "Entity saved." appears; SLO endpoint is visible on the Endpoints tab after save  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-059] Edit entity — REFEDS CoCo v2 checkbox toggle
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. Switch to REFEDS tab  
3. Check "CoCo v2" checkbox (if unchecked) or uncheck it (if checked)  
4. Click "Save"  
**Expected result:** Success toast "Entity saved." appears; the CoCo v2 state is toggled and persisted  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-060] Edit entity — multilingual description in Languages tab
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. Switch to Languages tab  
3. Add a description in Romanian: `Descriere în română`  
4. Click "Save"  
**Expected result:** Success toast "Entity saved." appears; Languages tab shows the Romanian description persisted  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-061] Entity validate page loads
**URL:** http://81.180.84.177:8092/entities/{id}/validate  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/{id}/validate  
**Expected result:** Entity validation page loads; shows validation results or a button to trigger validation  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-062] Entity metadata XML accessible
**URL:** http://81.180.84.177:8092/entities/{id}/metadata.xml  
**Prerequisites:** Logged in as admin@example.com; entity exists with metadata generated  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/{id}/metadata.xml  
**Expected result:** XML content is returned with correct Content-Type (application/xml or text/xml); valid SAML metadata XML structure visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-063] Edit entity — privacy URL validation
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. Switch to UI Info tab  
3. Enter an invalid Privacy URL: `not-a-url`  
4. Click "Save"  
**Expected result:** Validation error shown for the Privacy URL field indicating it must be a valid URL  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-064] Edit entity — status change from draft to pending
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity with status "draft" exists  
**Steps:**  
1. Navigate to the draft entity's edit page  
2. On General tab, change Status from "draft" to "pending"  
3. Click "Save"  
**Expected result:** Success toast "Entity saved." appears; entity status is updated to "pending"  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-065] Edit entity — status change from pending to active
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity with status "pending" exists  
**Steps:**  
1. Navigate to the pending entity's edit page  
2. On General tab, change Status from "pending" to "active"  
3. Click "Save"  
**Expected result:** Success toast "Entity saved." appears; entity status is updated to "active"  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 6. Entity Lifecycle — Suspend

### [TC-066] Suspend entity — open wizard from entity list
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; at least one active entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Find an active entity in the list  
3. Click "Suspend" button or action for that entity  
**Expected result:** EntitySuspendModal opens showing Step 1 (Impact); step indicators show 4 steps total  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-067] Suspend wizard — Step 1 Impact
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; EntitySuspendModal open at Step 1  
**Steps:**  
1. Open the suspend wizard for an active entity  
2. Read the content of Step 1 (Impact)  
**Expected result:** Step 1 shows the impact of suspending the entity (e.g., description of what will happen to the entity's memberships and users); "Next" button is available  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-068] Suspend wizard — Step 2 Memberships
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; EntitySuspendModal at Step 1  
**Steps:**  
1. In the suspend wizard at Step 1, click "Next"  
**Expected result:** Step 2 (Memberships) is shown; lists the federations the entity belongs to; "Next" and "Back" buttons available  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-069] Suspend wizard — Step 3 Notify
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; EntitySuspendModal at Step 2  
**Steps:**  
1. In the suspend wizard at Step 2, click "Next"  
**Expected result:** Step 3 (Notify) is shown; has an option to send notification emails; "Next" and "Back" buttons available  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-070] Suspend wizard — Step 4 Confirm and complete
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; EntitySuspendModal at Step 3  
**Steps:**  
1. In the suspend wizard at Step 3, click "Next"  
2. Step 4 (Confirm) is shown  
3. Click "Confirm" or "Suspend"  
**Expected result:** Entity is suspended; modal closes; page shows flash message or redirects; entity status changes to "suspended" in the list  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-071] Suspended entity appears with suspended status in list
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; entity was just suspended (TC-070)  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Find the recently suspended entity  
**Expected result:** Entity shows status "suspended" in the list; filter by "Suspended" status shows this entity  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-072] Suspend wizard — cancel/close at any step
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; EntitySuspendModal open  
**Steps:**  
1. Open the suspend wizard  
2. Click the close/cancel button (X or Cancel) at any step  
**Expected result:** Modal closes without suspending the entity; entity status remains unchanged  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-073] Session success flash shown after suspension
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; entity suspended via wizard  
**Steps:**  
1. Complete the suspend wizard (TC-070)  
2. Observe the page after modal closes  
**Expected result:** A success flash or toast message reads "Entity suspended successfully." visible on the page  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 7. Entity Lifecycle — Reactivate

### [TC-074] Reactivate entity — open wizard
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; at least one suspended entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Filter by status "Suspended" to find suspended entities  
3. Click "Reactivate" button or action for a suspended entity  
**Expected result:** EntityReactivateModal opens showing Step 1 (Impact); 4-step wizard indicator shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-075] Reactivate wizard — Steps 1–4
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; EntityReactivateModal open  
**Steps:**  
1. Read Step 1 (Impact); click "Next"  
2. Read Step 2 (Federation); click "Next"  
3. Read Step 3 (Notify); click "Next"  
4. Read Step 4 (Confirm); click "Confirm" or "Reactivate"  
**Expected result:** Each step loads correctly; final step reactivates the entity; modal closes; entity status changes back to "active"; success flash "Entity reactivated successfully." is shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-076] Reactivated entity shown as active in list
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; entity reactivated in TC-075  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Find the reactivated entity  
**Expected result:** Entity shows status "active"; it is no longer shown under "Suspended" filter  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-077] POST /entities/{id}/reactivate route accessible
**URL:** http://81.180.84.177:8092/entities/{id}/reactivate  
**Prerequisites:** Logged in as admin@example.com; suspended entity with known ID  
**Steps:**  
1. Trigger reactivation through the UI for a suspended entity  
2. Monitor network requests (browser dev tools) to confirm POST to /entities/{id}/reactivate  
**Expected result:** A POST request to /entities/{id}/reactivate is made; response is 200 or redirect; entity is reactivated  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-078] Reactivate wizard — cancel without reactivating
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; suspended entity exists; wizard open  
**Steps:**  
1. Open reactivate wizard for a suspended entity  
2. Click "Cancel" or "X" on any step  
**Expected result:** Modal closes; entity remains suspended; no status change  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 8. Entity Lifecycle — Delete / Restore

### [TC-079] Soft-delete entity — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to an entity's edit page  
2. Click the "Delete" button  
**Expected result:** SweetAlert2 dialog appears with title "Delete entity?" and text "This cannot be undone." with Confirm and Cancel buttons  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-080] Soft-delete entity — cancel
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; SweetAlert shown (TC-079)  
**Steps:**  
1. On the "Delete entity?" SweetAlert dialog, click "Cancel"  
**Expected result:** Dialog closes; entity is not deleted; page remains on the entity edit page  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-081] Soft-delete entity — confirm
**URL:** http://81.180.84.177:8092/entities/{id}/edit  
**Prerequisites:** Logged in as admin@example.com; SweetAlert shown  
**Steps:**  
1. On the "Delete entity?" SweetAlert dialog, click "Confirm" (or the confirm button)  
**Expected result:** Entity is soft-deleted; user is redirected to /entities; deleted entity no longer appears in the active list; success flash/toast shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-082] Trashed entities list accessible
**URL:** http://81.180.84.177:8092/entities/trashed  
**Prerequisites:** Logged in as admin@example.com; at least one entity has been soft-deleted  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/trashed  
**Expected result:** Trashed entities page loads; shows list of soft-deleted entities; previously deleted entity visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-083] Restore soft-deleted entity — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/entities/trashed  
**Prerequisites:** Logged in as admin@example.com; trashed entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/trashed  
2. Click "Restore" for a trashed entity  
**Expected result:** SweetAlert2 dialog appears with title "Restore this entity?" with Confirm and Cancel buttons  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-084] Restore entity — confirm
**URL:** http://81.180.84.177:8092/entities/trashed  
**Prerequisites:** Logged in as admin@example.com; "Restore this entity?" SweetAlert shown  
**Steps:**  
1. Click "Confirm" on the "Restore this entity?" dialog  
**Expected result:** Entity is restored; page refreshes or redirects; entity no longer in trashed list; entity visible in the main entities list  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-085] Force-delete entity — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/entities/trashed  
**Prerequisites:** Logged in as admin@example.com; trashed entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/trashed  
2. Click "Force Delete" or "Permanently Delete" for a trashed entity  
**Expected result:** SweetAlert2 dialog appears with title "Permanently delete?" and text "The entity will be removed forever." with Confirm and Cancel buttons  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-086] Force-delete entity — confirm
**URL:** http://81.180.84.177:8092/entities/trashed  
**Prerequisites:** Logged in as admin@example.com; "Permanently delete?" SweetAlert shown  
**Steps:**  
1. Click "Confirm" on the "Permanently delete?" dialog  
**Expected result:** Entity is permanently removed from the database; entity disappears from the trashed list; no way to restore it; success message shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 9. Entity Metadata & Validation

### [TC-087] Validate entity — trigger validation
**URL:** http://81.180.84.177:8092/entities/{id}/validate  
**Prerequisites:** Logged in as admin@example.com; entity exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/{id}/validate  
2. Click the "Validate" or "Run Validation" button if present  
**Expected result:** Validation runs; results page shows pass/fail status for each validation rule  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-088] Entity metadata XML download
**URL:** http://81.180.84.177:8092/entities/{id}/metadata.xml  
**Prerequisites:** Logged in as admin@example.com; entity with metadata  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/{id}/metadata.xml  
2. Observe the response  
**Expected result:** Browser renders or downloads XML content; Content-Type header is application/xml or text/xml; XML contains `<EntityDescriptor` SAML element  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-089] Reject entity — SweetAlert with reason textarea
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; entity with status "pending" exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Find a pending entity  
3. Click "Reject" action  
**Expected result:** SweetAlert2 dialog appears with title "Reject entity?" and a textarea input with placeholder "Enter reason..."  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-090] Reject entity — submit without reason shows error
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; "Reject entity?" SweetAlert open (from TC-089)  
**Steps:**  
1. Leave the reason textarea empty  
2. Click the confirm/reject button  
**Expected result:** SweetAlert shows validation message "A reason is required." preventing submission  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-091] Reject entity — with reason
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; "Reject entity?" SweetAlert open  
**Steps:**  
1. Type a reason in the textarea: `Entity does not meet compliance requirements`  
2. Click the confirm/reject button  
**Expected result:** Entity is rejected (status changes to "draft" or "rejected"); dialog closes; success feedback shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-092] Approve pending entity
**URL:** http://81.180.84.177:8092/entities  
**Prerequisites:** Logged in as admin@example.com; entity with status "pending" exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities  
2. Find a pending entity  
3. Click "Approve" action  
**Expected result:** Entity status changes to "active"; success feedback shown; entity appears in active filter  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 10. Entity Import

### [TC-093] XML import page loads
**URL:** http://81.180.84.177:8092/entities/import/xml  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/import/xml  
**Expected result:** XML import page loads; shows file upload or textarea for XML input; no errors  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-094] Array import page loads
**URL:** http://81.180.84.177:8092/entities/import/array  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/import/array  
**Expected result:** Array import page loads; shows form for importing entities from array/JSON format; no errors  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-095] Import preview page loads
**URL:** http://81.180.84.177:8092/entities/import/preview  
**Prerequisites:** Logged in as admin@example.com; import data submitted  
**Steps:**  
1. Submit a valid XML import on /entities/import/xml  
2. Observe the redirect to the preview page  
**Expected result:** Preview page at /entities/import/preview loads; shows a preview of entities to be imported with details  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-096] Import confirm page loads
**URL:** http://81.180.84.177:8092/entities/import/confirm  
**Prerequisites:** Logged in as admin@example.com; preview reviewed  
**Steps:**  
1. From the import preview page, click "Confirm Import" or equivalent button  
**Expected result:** POST to /entities/import/confirm is made; entities are imported; success message shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-097] XML import — invalid XML shows error
**URL:** http://81.180.84.177:8092/entities/import/xml  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/import/xml  
2. Submit invalid XML content (e.g., `<not valid xml>`)  
**Expected result:** Validation error shown; user stays on import page; no entities are created  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-098] XML import — valid SAML metadata XML
**URL:** http://81.180.84.177:8092/entities/import/xml  
**Prerequisites:** Logged in as admin@example.com; valid SAML EntityDescriptor XML ready  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/import/xml  
2. Submit valid SAML EntityDescriptor XML  
**Expected result:** Redirected to preview page; entity data parsed from XML shown in preview  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-099] Import — empty submission shows error
**URL:** http://81.180.84.177:8092/entities/import/xml  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/entities/import/xml  
2. Submit the form without providing any XML  
**Expected result:** Validation error shown indicating XML input is required; no redirect to preview  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 11. Federation List

### [TC-100] Federation list page loads
**URL:** http://81.180.84.177:8092/federations  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations  
**Expected result:** Federation list loads; "Default Federation" is visible; page title or heading shows "Federations"  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-101] Federation list shows Default Federation with active status
**URL:** http://81.180.84.177:8092/federations  
**Prerequisites:** Logged in as admin@example.com; seeder run  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations  
2. Locate "Default Federation" in the list  
**Expected result:** "Default Federation" shows status "active"; member count visible (showing 12 entities)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-102] Create federation button navigates to create form
**URL:** http://81.180.84.177:8092/federations  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations  
2. Click "Create" or "New Federation" button  
**Expected result:** Browser navigates to http://81.180.84.177:8092/federations/create  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-103] Click federation name navigates to show page
**URL:** http://81.180.84.177:8092/federations  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations  
2. Click the "Default Federation" name or link  
**Expected result:** Browser navigates to http://81.180.84.177:8092/federations/{id} federation show page  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-104] Trashed federations link accessible
**URL:** http://81.180.84.177:8092/federations/trashed  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/trashed  
**Expected result:** Trashed federations page loads; shows list of deleted federations (or empty state if none deleted); no 404  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-105] Operator can view federation list
**URL:** http://81.180.84.177:8092/federations  
**Prerequisites:** Logged in as operator@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations  
**Expected result:** Federation list loads for Operator; "Default Federation" visible; page accessible (no 403)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 12. Federation Create

### [TC-106] Federation create page loads
**URL:** http://81.180.84.177:8092/federations/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/create  
**Expected result:** Create federation form loads; fields for federation name, description, status, and other attributes are visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-107] Create federation — required fields validation
**URL:** http://81.180.84.177:8092/federations/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/create  
2. Leave the federation name empty  
3. Click "Save" or "Create"  
**Expected result:** Validation error shown for the name field (required); federation is not created  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-108] Create federation successfully
**URL:** http://81.180.84.177:8092/federations/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/create  
2. Enter Federation Name: `Test Federation`  
3. Enter Description: `A test federation for UI testing`  
4. Set Status: "active"  
5. Click "Save"  
**Expected result:** Federation "Test Federation" is created; success flash/session message shown; user redirected to the federation show page or federation list  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-109] New federation appears in federation list
**URL:** http://81.180.84.177:8092/federations  
**Prerequisites:** Logged in as admin@example.com; "Test Federation" created in TC-108  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations  
**Expected result:** Both "Default Federation" and "Test Federation" are visible in the list  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-110] Create federation with inactive status
**URL:** http://81.180.84.177:8092/federations/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/create  
2. Enter Federation Name: `Inactive Test Federation`  
3. Set Status: "inactive"  
4. Click "Save"  
**Expected result:** Federation is created with "inactive" status; visible in federation list with "inactive" badge  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-111] Create federation — signing driver field (single driver active)
**URL:** http://81.180.84.177:8092/federations/create  
**Prerequisites:** Logged in as admin@example.com; only `FILE_SIGNING_IS_ACTIVE=true` in `.env` (default); `SOFTHSM_SIGNING_IS_ACTIVE` not set or false  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/create  
2. Inspect the form for a signing driver field  
**Expected result:** No signing driver selector is visible; the form contains a hidden `<input type="hidden" name="signing_driver" value="file">` element (verifiable via browser dev tools); the form submits successfully without user action on the driver field  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 13. Federation Show — General Tab

### [TC-111] Federation show page loads with General tab
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; Default Federation exists  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations  
2. Click "Default Federation"  
**Expected result:** Federation show page loads; General tab is active by default; shows federation name, description, status, and other details  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-112] Federation show — all 7 tabs visible
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to the Default Federation show page  
2. Count and verify tab names  
**Expected result:** 7 tabs visible: General, Membership, Metadata, Signing Keys, Attributes, Validators, Rules  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-113] Federation General tab — inline edit mode toggle
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; Default Federation show page open  
**Steps:**  
1. On the General tab, click "Edit" button  
**Expected result:** Alpine.js `editMode` activates; federation fields (name, description, status) become editable input fields; "Save" and "Cancel" buttons appear  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-114] Federation General tab — save inline edit
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; in edit mode (TC-113)  
**Steps:**  
1. Change the federation description to `Updated description`  
2. Click "Save"  
**Expected result:** PATCH request sent to /federations/{id}; success session flash shown; editMode deactivates; description shows `Updated description`  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-115] Federation General tab — cancel inline edit
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; in edit mode  
**Steps:**  
1. Change the federation name to `Changed Name`  
2. Click "Cancel"  
**Expected result:** editMode deactivates; original federation name is restored; no PATCH request made; no changes saved  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-116] Federation General tab — send email to federation
**URL:** http://81.180.84.177:8092/federations/{id}/mail  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to the Default Federation show page  
2. Find and click the "Send Email" or "Mail" link/button (or navigate to /federations/{id}/mail)  
**Expected result:** Email compose page loads; form allows composing and sending an email to federation members  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-117] Federation mail log accessible
**URL:** http://81.180.84.177:8092/federations/{id}/mail/log  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/{id}/mail/log  
**Expected result:** Mail log page loads; shows list of sent emails for this federation (or empty state); no 404 or 500  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 14. Federation Show — Membership Tab

### [TC-118] Federation Membership tab loads
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; Default Federation with 12 members  
**Steps:**  
1. Navigate to Default Federation show page  
2. Click "Membership" tab  
**Expected result:** Membership tab shows active members list; 12 entities listed as active members; sections for IdPs and SPs shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-119] Membership tab — pending entities section
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; at least one pending membership request exists  
**Steps:**  
1. Navigate to Default Federation Membership tab  
2. Look for pending membership requests section  
**Expected result:** Pending section shows any entities with pending membership status; approve/reject actions available  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-120] Membership — approve pending entity (Admin)
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; entity with pending federation membership exists  
**Steps:**  
1. On Membership tab, find a pending entity  
2. Click "Approve" for that entity  
**Expected result:** Entity moves from pending to active membership; success feedback shown; entity appears in the active members list  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-121] Membership — reject pending entity (Admin only)
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; entity with pending membership  
**Steps:**  
1. On Membership tab, find a pending entity  
2. Click "Reject" for that entity  
3. SweetAlert "Reject entity?" dialog appears with textarea; enter reason: `Does not meet federation requirements`  
4. Click Confirm  
**Expected result:** Entity membership is rejected; entity removed from pending list; success feedback shown; the reason is stored  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-122] Membership — Operator cannot approve/reject requests
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as operator@example.com; pending membership exists  
**Steps:**  
1. Navigate to Default Federation Membership tab as Operator  
2. Look for Approve/Reject buttons on pending entities  
**Expected result:** Approve and Reject buttons are either hidden, disabled, or absent for the Operator role (Operator lacks federation.approveRequest and federation.rejectRequest permissions)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-123] Membership — remove active entity from federation
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; entity is active member of Default Federation  
**Steps:**  
1. On Membership tab, find an active member entity  
2. Click "Remove" action for that entity  
**Expected result:** SweetAlert2 dialog with title "Remove from federation?" appears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-124] Membership — confirm remove entity from federation
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; "Remove from federation?" SweetAlert shown  
**Steps:**  
1. Click "Confirm" on the "Remove from federation?" dialog  
**Expected result:** Entity is removed from federation membership; entity disappears from active members list; success feedback shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-125] Membership — cancel remove from federation
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; "Remove from federation?" SweetAlert shown  
**Steps:**  
1. Click "Cancel" on the "Remove from federation?" dialog  
**Expected result:** Dialog closes; entity remains as active member; membership count unchanged  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-126] Membership — add entity to federation
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; an entity exists that is NOT in Default Federation  
**Steps:**  
1. On Membership tab, click "Add Entity" or "Add Member" button  
2. Search for or select an entity that is not yet a member  
3. Confirm addition  
**Expected result:** Entity is added as a member of the federation; appears in the active or pending members list  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-127] Membership tab — IdP sub-list shows only IdPs
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. On Membership tab, locate the IdP section or filter  
2. View the IdP member list  
**Expected result:** Only IdP-type entities appear in the IdP section; count = 6 (matching seeded data)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-128] Membership tab — SP sub-list shows only SPs
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. On Membership tab, locate the SP section or filter  
2. View the SP member list  
**Expected result:** Only SP-type entities appear in the SP section; count = 6 (matching seeded data)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-129] Membership — entity link navigates to entity edit
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; Membership tab visible with members  
**Steps:**  
1. On Membership tab, click the name of any active member entity  
**Expected result:** Browser navigates to /entities/{id}/edit for that entity  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-130] Membership — pending entity shows approve and reject buttons (Admin)
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; at least one pending entity membership  
**Steps:**  
1. On Membership tab, find a pending entity in the pending section  
2. Observe available action buttons  
**Expected result:** Both "Approve" and "Reject" buttons are visible for the Admin role for each pending membership  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 15. Federation Show — Signing Keys Tab

### [TC-200] Signing Keys tab loads — no key uploaded
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; federation has no signing key pair uploaded  
**Steps:**  
1. Navigate to a federation's show page  
2. Click the **Signing Keys** tab  
**Expected result:** Tab loads without errors; both status cards show grey border and "Not uploaded" badge; an info alert is visible stating metadata will be generated unsigned; the upload form is visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-201] Upload key only — wizard advances to Step 2
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; no key pair uploaded; a PEM private key file ready  
**Steps:**  
1. On the Signing Keys tab, upload a PEM private key file (`.key` or `.pem` containing only `-----BEGIN PRIVATE KEY-----`)  
2. Click Upload  
**Expected result:** Wizard advances to Step 2; message shows "Private key ready — now upload the matching certificate"; no key written to disk yet; Private Key card still shows "Not uploaded"  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-202] Upload mismatched certificate in Step 2 — rejected
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Wizard at Step 2 (TC-201); a certificate that does NOT match the Step 1 key is ready  
**Steps:**  
1. Upload a certificate that was generated with a different private key  
2. Click Upload  
**Expected result:** An error message is displayed (e.g. "The certificate does not match the private key"); neither key nor certificate is stored; wizard remains at Step 2; user can retry  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-203] Upload matching certificate in Step 2 — pair stored
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Wizard at Step 2 (TC-201); the matching certificate for the Step 1 key is ready  
**Steps:**  
1. Upload the matching certificate  
2. Click Upload  
**Expected result:** Both credentials stored atomically; wizard resets to Step 1; Private Key card shows green border and "Stored" badge; Certificate card shows green border and "Stored" badge; info alert about unsigned metadata is gone  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-204] Key info modal
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; key pair uploaded (TC-203)  
**Steps:**  
1. On the Signing Keys tab, click the **ⓘ** info button on the Private Key card  
**Expected result:** Modal opens; shows key type (e.g. RSA) and bit length (e.g. 2048 bits); PEM preview block visible showing first/last 50 characters; upload timestamp shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-205] Certificate info modal and download
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; key pair uploaded  
**Steps:**  
1. Click the **ⓘ** info button on the Certificate card  
2. Review the modal  
3. Click **Download .crt**  
**Expected result:** Modal shows subject, issuer, serial number, valid from/to dates with coloured expiry badge, full PEM text; clicking Download .crt triggers a file download named `federation-{slug}.crt`  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-206] Remove key pair
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; key pair uploaded  
**Steps:**  
1. On the Signing Keys tab, click **Remove Key Pair**  
2. Confirm the prompt  
**Expected result:** Both credentials deleted; Private Key and Certificate cards return to grey "Not uploaded" state; unsigned-metadata warning alert reappears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-207] Metadata tab shows "no signing key" alert when no pair uploaded
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; no signing key pair on this federation  
**Steps:**  
1. Navigate to the federation's Metadata tab  
2. Find the Sign Metadata card  
**Expected result:** An alert is shown: "No signing key available. Upload a key pair in the Signing Keys tab." A **Recheck** button is present; no Sign button is shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-208] Metadata tab shows signing label when key pair is present
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; key pair uploaded (TC-203)  
**Steps:**  
1. Navigate to the federation's Metadata tab  
2. Find the Sign Metadata card  
**Expected result:** Card shows "Will sign using: **federation key**"; no "no signing key" alert; Sign Metadata button is enabled  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 16. Federation Show — Metadata Tab

### [TC-131] Federation Metadata tab loads
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to Default Federation show page  
2. Click "Metadata" tab  
**Expected result:** Metadata tab loads; shows metadata generation section, download link, and eduGAIN panel  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-132] Regenerate metadata — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; Metadata tab open  
**Steps:**  
1. On Metadata tab, click "Regenerate Metadata" button  
**Expected result:** SweetAlert2 dialog appears with title "Regenerate metadata?" and text "This will replace the current signed metadata." with Confirm and Cancel buttons  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-133] Regenerate metadata — confirm
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; "Regenerate metadata?" SweetAlert open; federation has a signing key pair uploaded  
**Steps:**  
1. Click "Confirm" on the "Regenerate metadata?" dialog  
2. Wait for the queue worker to process the job (or run `php artisan queue:work --once`)  
**Expected result:** Metadata generation is queued; info toast "Metadata generation queued." appears top-right; dialog closes. After processing: the "Last signed" timestamp on the Metadata tab updates; the metadata feed at `/metadata/{slug}/feed` returns signed XML containing `<ds:Signature>`; the audit log shows a `metadata_generated` entry. No `metadata_sign_failed` entry in the audit log.  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-134] Regenerate metadata — cancel
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; "Regenerate metadata?" SweetAlert open  
**Steps:**  
1. Click "Cancel" on the "Regenerate metadata?" dialog  
**Expected result:** Dialog closes; no metadata regeneration is triggered; no toast shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-135] Download federation metadata
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; federation metadata has been generated  
**Steps:**  
1. On Metadata tab, click "Download Metadata" link  
**Expected result:** Browser downloads or displays the federation metadata XML file; response contains valid XML with multiple EntityDescriptor elements  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-136] Federation Metadata page via /metadata route
**URL:** http://81.180.84.177:8092/metadata  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/metadata  
**Expected result:** Metadata listing page loads; shows metadata generation interface for federations; no 403 or 404  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-137] Post-signing integrity check failure logged to audit log
**URL:** http://81.180.84.177:8092/audit  
**Prerequisites:** Logged in as admin@example.com; ability to trigger a simulated sign failure (e.g. by temporarily renaming the signing key file to make xmlsectool fail, or by running `php artisan tinker` to manually call `SignedMetadataValidator` with mismatched XML)  
**Notes:** This is an exceptional-path test. In a normal deployment this scenario should never occur. Its purpose is to verify that the integrity check fires and that the audit log records the failure correctly.  
**Steps:**  
1. Simulate a signing failure (corrupt or remove the key file, or call the validator manually via tinker with mismatched input/output XML)  
2. Trigger metadata generation for the affected federation  
3. Navigate to the Audit Log  
**Expected result:** An audit log entry with action `metadata_sign_failed` is present, showing which federation and which check failed; the previously cached metadata at `/metadata/{slug}/feed` is unchanged (not replaced); no `metadata_generated` success entry for this attempt  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 17. Federation Show — Attributes Tab

### [TC-137] Federation Attributes tab loads
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to Default Federation show page  
2. Click "Attributes" tab  
**Expected result:** Attributes tab loads; shows list of required attributes for entities in the federation (or empty state)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-138] Federation Attributes tab — add required attribute
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; Attributes tab open  
**Steps:**  
1. On Attributes tab, click "Add Attribute" or equivalent  
2. Select or enter an attribute name  
3. Save  
**Expected result:** Attribute is added to the federation's required attributes list; visible in the tab  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-139] Federation Attributes tab — remove attribute
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; at least one attribute added  
**Steps:**  
1. On Attributes tab, click "Remove" for an attribute  
**Expected result:** Attribute is removed from the list; no longer required for entities in this federation  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-140] Global Attributes page loads
**URL:** http://81.180.84.177:8092/attributes  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/attributes  
**Expected result:** Attributes page loads; lists all SAML attributes defined in the system; no 403 or 404  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 18. Federation Show — Validators Tab

### [TC-141] Federation Validators tab loads
**URL:** http://81.180.84.177:8092/federations/{id}/validators  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to Default Federation show page  
2. Click "Validators" tab  
**Expected result:** Validators tab loads; shows list of external validation endpoints (or empty state); "Create Validator" button visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-142] Create validator page loads
**URL:** http://81.180.84.177:8092/federations/{id}/validators/create  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. On Validators tab, click "Create Validator" or "Add Validator"  
**Expected result:** Validator create form loads at /federations/{id}/validators/create; fields for validator URL and settings visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-143] Delete validator — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/federations/{id}/validators  
**Prerequisites:** Logged in as admin@example.com; at least one validator exists  
**Steps:**  
1. On Validators tab, click "Delete" for a validator  
**Expected result:** SweetAlert2 dialog with title "Delete validator?" appears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-144] Federation Policies tab loads
**URL:** http://81.180.84.177:8092/federations/{id}/policies  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/{id}/policies  
**Expected result:** Registration policies page loads; shows list of registration policies for the federation; "Create Policy" button visible  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 19. Federation Lifecycle — Deactivate

### [TC-145] Deactivate federation — open wizard
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; Default Federation is active  
**Steps:**  
1. Navigate to Default Federation show page  
2. Find and click "Deactivate" button  
**Expected result:** FederationDeactivateModal opens; Step 1 (Impact) shown; step indicator shows up to 6 steps  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-146] Deactivate wizard — Step 1 Impact
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; FederationDeactivateModal at Step 1  
**Steps:**  
1. Read Step 1 (Impact) content  
2. Observe available navigation buttons  
**Expected result:** Step 1 shows the impact summary of deactivating the federation; "Next" button available; "Cancel" option available  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-147] Deactivate wizard — Step 2 Active Entities
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; at Step 1 of deactivate wizard  
**Steps:**  
1. Click "Next" from Step 1  
**Expected result:** Step 2 (Active Entities) shows list of currently active entities that will be affected; "Next" and "Back" available  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-148] Deactivate wizard — step 3 skipped when no pending entities
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; no pending entities in Default Federation  
**Steps:**  
1. Proceed through deactivate wizard to Step 2  
2. Click "Next"  
**Expected result:** If no pending entities exist, Step 3 (Pending Entities) is skipped; wizard goes directly to Step 4 (Jobs) or Step 5 (Notify)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-149] Deactivate wizard — complete deactivation
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; at final Confirm step of deactivate wizard  
**Steps:**  
1. Proceed through all deactivate wizard steps  
2. On the final Confirm step, click "Confirm" or "Deactivate"  
**Expected result:** Federation is deactivated; modal closes; federation status changes to "inactive" in the federation list/show page; success feedback shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 20. Federation Lifecycle — Delete / Restore

### [TC-150] Delete federation — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; a non-Default federation exists (e.g., "Test Federation" from TC-108)  
**Steps:**  
1. Navigate to "Test Federation" show page  
2. Click "Delete" button  
**Expected result:** SweetAlert2 dialog with title "Delete federation?" and text "This cannot be undone." appears with Confirm and Cancel buttons  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-151] Delete federation — confirm
**URL:** http://81.180.84.177:8092/federations/{id}  
**Prerequisites:** Logged in as admin@example.com; "Delete federation?" SweetAlert shown  
**Steps:**  
1. Click "Confirm" on "Delete federation?" dialog  
**Expected result:** Federation is soft-deleted; redirected to /federations; "Test Federation" no longer visible in active list  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-152] Trashed federation visible in /federations/trashed
**URL:** http://81.180.84.177:8092/federations/trashed  
**Prerequisites:** Logged in as admin@example.com; "Test Federation" soft-deleted (TC-151)  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/federations/trashed  
**Expected result:** "Test Federation" appears in the trashed federations list with option to Restore or Force Delete  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-153] Restore federation
**URL:** http://81.180.84.177:8092/federations/trashed  
**Prerequisites:** Logged in as admin@example.com; "Test Federation" in trash  
**Steps:**  
1. On /federations/trashed, click "Restore" for "Test Federation"  
2. Confirm the action  
**Expected result:** "Test Federation" is restored; appears in the active federation list at /federations  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-154] Force-delete federation — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/federations/trashed  
**Prerequisites:** Logged in as admin@example.com; a federation in trash exists  
**Steps:**  
1. Navigate to /federations/trashed  
2. Click "Force Delete" or "Permanently Delete" for a trashed federation  
**Expected result:** SweetAlert2 dialog with title "Permanently delete federation?" and text "All membership records will be removed." appears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 21. Certificates Monitor

### [TC-155] Certificates monitor page loads
**URL:** http://81.180.84.177:8092/certificates/monitor  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/certificates/monitor  
**Expected result:** Certificate monitoring page loads; shows certificate list or status dashboard; page title mentions "Certificates"  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-156] Certificates monitor — critical certificates displayed
**URL:** http://81.180.84.177:8092/certificates/monitor  
**Prerequisites:** Logged in as admin@example.com; 2 entities with certs expiring in 10 days  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/certificates/monitor  
2. Look for critical/expiring certificates section  
**Expected result:** 2 certificates are flagged as critical (10-day expiry); they are highlighted with red or warning styling  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-157] Certificates monitor — healthy certificates displayed
**URL:** http://81.180.84.177:8092/certificates/monitor  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/certificates/monitor  
2. Look for healthy certificates section  
**Expected result:** 10 healthy certificates are shown with green or normal styling  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-158] Certificates monitor — cert table visible
**URL:** http://81.180.84.177:8092/certificates/monitor  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/certificates/monitor  
2. Observe the certificate table (from _cert_table.blade.php partial)  
**Expected result:** Certificate table shows columns for entity name, certificate subject/expiry date, severity, and actions  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-159] Send certificate expiry notifications — SweetAlert
**URL:** http://81.180.84.177:8092/certificates/monitor  
**Prerequisites:** Logged in as admin@example.com; critical certificates exist  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/certificates/monitor  
2. Click "Send Notifications" or "Notify" button  
**Expected result:** SweetAlert2 dialog with title "Send notifications?" and text "Expiry alerts will be sent to all affected contacts." appears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-160] Send certificate notifications — confirm
**URL:** http://81.180.84.177:8092/certificates/monitor  
**Prerequisites:** Logged in as admin@example.com; "Send notifications?" SweetAlert open  
**Steps:**  
1. Click "Confirm" on "Send notifications?" dialog  
**Expected result:** Notifications are sent; success toast appears (Livewire dispatch); dialog closes  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-161] Certificates monitor accessible to Operator
**URL:** http://81.180.84.177:8092/certificates/monitor  
**Prerequisites:** Logged in as operator@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/certificates/monitor  
**Expected result:** Certificate monitoring page loads for Operator without 403 error  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 22. Metadata Page

### [TC-162] Metadata page loads (Admin)
**URL:** http://81.180.84.177:8092/metadata  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/metadata  
**Expected result:** Metadata page loads; shows federation metadata management (requires metadata.generate permission); no 403 or 404  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-163] Metadata page loads (Operator)
**URL:** http://81.180.84.177:8092/metadata  
**Prerequisites:** Logged in as operator@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/metadata  
**Expected result:** Metadata page loads for Operator (Operator has metadata.generate permission); no 403  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-164] Metadata page not accessible to Guest
**URL:** http://81.180.84.177:8092/metadata  
**Prerequisites:** Logged in as a Guest-role user (new SAML login user with Guest role)  
**Steps:**  
1. Log in with a Guest-role account  
2. Navigate to http://81.180.84.177:8092/metadata  
**Expected result:** 403 Forbidden or redirect to dashboard; metadata page is not accessible; sidebar does not show "Metadata" link for Guest  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-165] Sidebar Metadata link visible for Admin and Operator
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to /dashboard as Admin; check sidebar for "Metadata" link  
2. Log out; log in as operator@example.com; check sidebar for "Metadata" link  
**Expected result:** "Metadata" is visible in sidebar for both Admin and Operator  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 23. Users

### [TC-166] Users list page loads (Admin)
**URL:** http://81.180.84.177:8092/users  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/users  
**Expected result:** Users list page loads; shows at least admin@example.com and operator@example.com; page title shows "Users"  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-167] Suspend user — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/users  
**Prerequisites:** Logged in as admin@example.com; at least one other active user visible (e.g., operator@example.com)  
**Steps:**  
1. Navigate to /users  
2. Find operator@example.com in the list  
3. Click "Suspend" action for that user  
**Expected result:** SweetAlert2 dialog with title "Suspend this user?" appears with Confirm and Cancel buttons  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-168] Suspend user — confirm
**URL:** http://81.180.84.177:8092/users  
**Prerequisites:** Logged in as admin@example.com; "Suspend this user?" SweetAlert open  
**Steps:**  
1. Click "Confirm" on "Suspend this user?" dialog  
**Expected result:** User is suspended; status badge changes to "suspended"; Operator cannot log in while suspended  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-169] Reinstate user — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/users  
**Prerequisites:** Logged in as admin@example.com; operator@example.com is suspended  
**Steps:**  
1. Navigate to /users  
2. Find suspended operator@example.com  
3. Click "Reinstate" action  
**Expected result:** SweetAlert2 dialog with title "Reinstate this user?" appears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-170] Reinstate user — confirm
**URL:** http://81.180.84.177:8092/users  
**Prerequisites:** Logged in as admin@example.com; "Reinstate this user?" SweetAlert open  
**Steps:**  
1. Click "Confirm" on "Reinstate this user?" dialog  
**Expected result:** User is reinstated; status badge changes back to "active"; Operator can log in again  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-171] Users page accessible to Operator
**URL:** http://81.180.84.177:8092/users  
**Prerequisites:** Logged in as operator@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/users  
**Expected result:** Users page loads for Operator (has user.view permission); user list visible; no 403  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-172] Operator cannot create/edit/delete users
**URL:** http://81.180.84.177:8092/users  
**Prerequisites:** Logged in as operator@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/users  
2. Look for "Create User", "Edit", and "Delete" buttons  
**Expected result:** Create, Edit, and Delete user buttons are hidden or absent for Operator (lacks user.create, user.edit, user.delete permissions); Operator can only view  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 24. Audit Log

### [TC-173] Audit log page loads (Admin)
**URL:** http://81.180.84.177:8092/audit  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/audit  
**Expected result:** Audit log page loads; shows list of audit events (login, entity changes, federation changes, etc.); no 403 or 500  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-174] Audit log shows recent actions
**URL:** http://81.180.84.177:8092/audit  
**Prerequisites:** Logged in as admin@example.com; several actions performed during testing  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/audit  
2. Observe the most recent log entries  
**Expected result:** Recent actions (entity saves, federation changes, user actions) appear in the audit log with timestamps, user, and action type  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-175] Audit log accessible to Operator
**URL:** http://81.180.84.177:8092/audit  
**Prerequisites:** Logged in as operator@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/audit  
**Expected result:** Audit log loads for Operator (has user.view permission which includes audit access); no 403  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-176] Audit log sidebar link visible for Admin and Operator
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Check sidebar on dashboard as Admin; confirm "Audit Log" link present  
2. Log out; log in as operator@example.com; check sidebar for "Audit Log"  
**Expected result:** "Audit Log" is visible in sidebar for both Admin and Operator  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 25. Attributes

### [TC-177] Attributes page loads (Admin)
**URL:** http://81.180.84.177:8092/attributes  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/attributes  
**Expected result:** Attributes page loads; shows list of SAML attributes in the system; no 403 or 500  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-178] Deactivate attribute — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/attributes  
**Prerequisites:** Logged in as admin@example.com; at least one active attribute exists  
**Steps:**  
1. Navigate to /attributes  
2. Find an active attribute  
3. Click "Deactivate" for that attribute  
**Expected result:** SweetAlert2 dialog with title "Deactivate attribute?" and text "Existing assignments will be kept." appears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-179] Attributes page accessible to Operator
**URL:** http://81.180.84.177:8092/attributes  
**Prerequisites:** Logged in as operator@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/attributes  
**Expected result:** Attributes page loads for Operator (has entity.view permission which includes attributes); no 403  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-180] Attributes sidebar link visible for Guest
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as a Guest-role user  
**Steps:**  
1. Navigate to dashboard as Guest  
2. Check sidebar for "Attributes" link  
**Expected result:** "Attributes" is visible in the sidebar for Guest (Guest has entity.view permission)  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 26. Mail Templates

### [TC-181] Mail templates page loads (Admin)
**URL:** http://81.180.84.177:8092/mail/templates  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/mail/templates  
**Expected result:** Mail templates page loads; shows list of email templates; no 403 or 404  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-182] Mail templates not accessible to Guest
**URL:** http://81.180.84.177:8092/mail/templates  
**Prerequisites:** Logged in as a Guest-role user  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/mail/templates  
**Expected result:** 403 Forbidden or redirect; Mail Templates link not shown in sidebar for Guest  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-183] Delete mail template — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/mail/templates  
**Prerequisites:** Logged in as admin@example.com; at least one mail template exists  
**Steps:**  
1. Navigate to /mail/templates  
2. Click "Delete" for a template  
**Expected result:** SweetAlert2 dialog with title "Delete this template?" appears with Confirm and Cancel buttons  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-184] Send email — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/federations/{id}/mail  
**Prerequisites:** Logged in as admin@example.com; compose email form filled  
**Steps:**  
1. Navigate to federation email compose page (/federations/{id}/mail)  
2. Fill in subject and body  
3. Click "Send"  
**Expected result:** SweetAlert2 dialog with title "Send email?" appears with Confirm and Cancel buttons  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 27. Compliance Rules

### [TC-185] Compliance rules page loads (Admin)
**URL:** http://81.180.84.177:8092/rules  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/rules  
**Expected result:** Compliance rules page loads; shows list of rules or rule management interface; no 403 or 500  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-186] Compliance rules not accessible to Guest
**URL:** http://81.180.84.177:8092/rules  
**Prerequisites:** Logged in as a Guest-role user  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/rules  
**Expected result:** 403 Forbidden or redirect to dashboard; "Compliance Rules" not in sidebar for Guest  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-187] Delete registration policy — SweetAlert confirmation
**URL:** http://81.180.84.177:8092/federations/{id}/policies  
**Prerequisites:** Logged in as admin@example.com; at least one registration policy exists  
**Steps:**  
1. Navigate to /federations/{id}/policies  
2. Click "Delete" for a registration policy  
**Expected result:** SweetAlert2 dialog with title "Delete registration policy?" appears  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 28. Scheduler

### [TC-188] Scheduler page loads (Admin)
**URL:** http://81.180.84.177:8092/scheduler  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/scheduler  
**Expected result:** Scheduler configuration page loads; shows scheduled job settings; no 403 or 500  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-189] Scheduler save — success flash
**URL:** http://81.180.84.177:8092/scheduler  
**Prerequisites:** Logged in as admin@example.com; scheduler page loaded  
**Steps:**  
1. Navigate to /scheduler  
2. Modify a scheduler setting (e.g., change frequency)  
3. Click "Save"  
**Expected result:** Session success flash appears; scheduler settings are persisted  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-190] Scheduler not accessible to Guest
**URL:** http://81.180.84.177:8092/scheduler  
**Prerequisites:** Logged in as a Guest-role user  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/scheduler  
**Expected result:** 403 Forbidden or redirect; "Scheduler" not visible in sidebar for Guest  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 29. Preferences

### [TC-191] Preferences page loads (Admin)
**URL:** http://81.180.84.177:8092/preferences  
**Prerequisites:** Logged in as admin@example.com  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/preferences  
**Expected result:** Preferences page loads; shows application preference settings; no 403 or 500  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-192] Preferences save — success flash
**URL:** http://81.180.84.177:8092/preferences  
**Prerequisites:** Logged in as admin@example.com; preferences page loaded  
**Steps:**  
1. Navigate to /preferences  
2. Modify any preference setting  
3. Click "Save"  
**Expected result:** Session success flash appears; preferences are persisted  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-193] Preferences not accessible to Guest
**URL:** http://81.180.84.177:8092/preferences  
**Prerequisites:** Logged in as a Guest-role user  
**Steps:**  
1. Navigate to http://81.180.84.177:8092/preferences  
**Expected result:** 403 Forbidden or redirect; "Preferences" not visible in sidebar for Guest  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## 30. Role-Based Access — Guest

### [TC-194] Guest sidebar shows only allowed items
**URL:** http://81.180.84.177:8092/dashboard  
**Prerequisites:** Logged in as a Guest-role user (create a test user via SAML or promote an account to Guest)  
**Steps:**  
1. Log in as a Guest-role user  
2. Navigate to /dashboard  
3. Inspect the sidebar for visible navigation items  
**Expected result:** Sidebar shows ONLY: Dashboard, Entities, Federations, Certificates, Attributes, Users, Audit Log. The following items are NOT visible: Metadata, Mail Templates, Compliance Rules, Scheduler, Preferences  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

### [TC-195] Guest cannot access restricted pages directly
**URL:** Multiple  
**Prerequisites:** Logged in as a Guest-role user  
**Steps:**  
1. As Guest, navigate directly to http://81.180.84.177:8092/metadata  
2. As Guest, navigate directly to http://81.180.84.177:8092/mail/templates  
3. As Guest, navigate directly to http://81.180.84.177:8092/rules  
4. As Guest, navigate directly to http://81.180.84.177:8092/scheduler  
5. As Guest, navigate directly to http://81.180.84.177:8092/preferences  
**Expected result:** Each URL returns 403 Forbidden or redirects to /dashboard; Guest cannot bypass sidebar restrictions by navigating directly; no page content from the restricted section is shown  
**Actual result:** _______________  
**Status:** [ ] Pass  [ ] Fail  [ ] Partial  

---

## Test Summary

| Section | Total | Pass | Fail | Partial | Not Run |
|---------|-------|------|------|---------|---------|
| 1. Authentication | 10 | | | | |
| 2. Dashboard | 5 | | | | |
| 3. Entity List & Filtering | 15 | | | | |
| 4. Entity Create | 20 | | | | |
| 5. Entity Edit | 15 | | | | |
| 6. Entity Lifecycle — Suspend | 8 | | | | |
| 7. Entity Lifecycle — Reactivate | 5 | | | | |
| 8. Entity Lifecycle — Delete/Restore | 8 | | | | |
| 9. Entity Metadata & Validation | 6 | | | | |
| 10. Entity Import | 7 | | | | |
| 11. Federation List | 6 | | | | |
| 12. Federation Create | 5 | | | | |
| 13. Federation Show — General | 7 | | | | |
| 14. Federation Show — Membership | 13 | | | | |
| 15. Federation Show — Metadata | 6 | | | | |
| 16. Federation Show — Attributes | 4 | | | | |
| 17. Federation Show — Validators | 4 | | | | |
| 18. Federation Lifecycle — Deactivate | 5 | | | | |
| 19. Federation Lifecycle — Delete/Restore | 5 | | | | |
| 20. Certificates Monitor | 7 | | | | |
| 21. Metadata Page | 4 | | | | |
| 22. Users | 7 | | | | |
| 23. Audit Log | 4 | | | | |
| 24. Attributes | 4 | | | | |
| 25. Mail Templates | 4 | | | | |
| 26. Compliance Rules | 3 | | | | |
| 27. Scheduler | 3 | | | | |
| 28. Preferences | 3 | | | | |
| 29. Role-Based Access — Guest | 2 | | | | |
| **TOTAL** | **195** | | | | |

---

## Bug Report Template

Use this template for any failing test cases:

**Bug ID:** BUG-___  
**TC Reference:** TC-___  
**Severity:** [ ] Critical  [ ] High  [ ] Medium  [ ] Low  
**Summary:** _______________  
**Steps to Reproduce:** _______________  
**Expected:** _______________  
**Actual:** _______________  
**Screenshot/Evidence:** _______________  
**Notes:** _______________  

---

*End of UI Test Sheet — 195 test cases*

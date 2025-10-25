**Karyalay ERP: CRM Visits Section – Functional Specification Document**

---

### 🚗 Module Name: CRM – Visits Section
The **Visits Section** of the CRM module is used to track field visits or on-site client interactions made by company representatives. It helps organizations maintain transparency, record on-ground activities, and link them to specific leads or projects.

This section integrates with **Leads**, **Meetings**, and the CRM **Calendar** to ensure real-time visibility of field operations.

---

## 📆 1. Functional Overview
The Visits module enables:
- Logging of on-site or field visits
- Recording visit details, outcomes, and related attachments
- Capturing real-time location coordinates during entry (optional)
- Scheduling follow-ups (calls, meetings, or tasks) post-visit
- Integration with the CRM calendar for date-based tracking

---

## ✨ 2. Feature List

### Employee Side:
- Add new visit entry manually or via mobile interface
- Attach images, invoices, or documents from site
- Record visit summary, time, and optional follow-up
- View past and upcoming visits in calendar

### Manager/Admin Side:
- View all visits across the organization
- Filter by employee, lead, or date
- Export visit logs and monitor on-ground performance
- Approve or review submitted visit reports

---

## 🧮 3. Database Structure Reference (from Schema Doc)
**Table:** `crm_visits`

| Field          | Type            | Description                                         |
|----------------|-----------------|-----------------------------------------------------|
| id             | INT, PK, AI      | Unique visit ID                                     |
| lead_id        | INT, FK NULL     | Linked lead (optional)                              |
| title          | VARCHAR(150)     | Visit subject/purpose                               |
| notes          | TEXT             | Visit details and observations                      |
| visit_date     | DATETIME         | Date/time of visit                                  |
| outcome        | TEXT NULL        | Summary or remarks from the visit                   |
| follow_up_date | DATE NULL        | Optional date for next step                         |
| follow_up_type | ENUM NULL        | Call / Meeting / Visit / Task                       |
| created_by     | INT, FK          | Employee who created the record                     |
| assigned_to    | INT, FK          | Responsible employee for the visit                  |
| location       | TEXT             | Address or GPS coordinates captured                 |
| attachment     | TEXT             | Photo or file uploaded during the visit             |
| created_at     | TIMESTAMP        | Entry timestamp                                     |
| updated_at     | TIMESTAMP NULL   | Update timestamp                                    |

---

## 🔗 4. Relationship with Other CRM Modules
| Related Module | Relationship Type | Purpose |
|----------------|-------------------|----------|
| Leads | Many-to-One | Visits are logged against leads to track client engagement |
| Meetings | Optional Link | Physical meetings may trigger visit logs |
| Tasks | Optional Link | Tasks can be generated post-visit for internal actions |

Each visit automatically updates the `last_contacted_at` field of the associated lead.

---

## 🧱 5. Frontend Pages
| Page | URL Route | Description | Access Role |
|------|------------|-------------|--------------|
| All Visits | `/crm/visits` | View all recorded visits | Admin, Manager |
| My Visits | `/crm/visits/my` | List visits by logged-in employee | Employee |
| Add Visit | `/crm/visits/add` | Create a new visit entry | Employee, Manager |
| Edit Visit | `/crm/visits/edit/:id` | Update existing visit record | Manager, Admin |
| Visit Details | `/crm/visits/view/:id` | View full visit log with map and attachments | All roles |

---

## 🚀 6. Backend Routes (PHP Endpoints)
| Method | Route | Purpose | Auth |
|--------|--------|----------|-------|
| GET | `/api/crm/visits` | Fetch all visit records | Yes |
| GET | `/api/crm/visits/:id` | Fetch single visit details | Yes |
| POST | `/api/crm/visits/add` | Add new visit entry | Yes |
| POST | `/api/crm/visits/update/:id` | Edit visit record | Yes |
| DELETE | `/api/crm/visits/delete/:id` | Soft delete a visit record | Admin |

---

## ⚙️ 7. Validations & Rules
- Visit date/time cannot be in the future
- Title and notes are mandatory
- Attachments must be image or PDF format (max 3MB)
- Follow-up type must be one of (Call, Meeting, Visit, Task)
- GPS location (if enabled) captured automatically

---

## 📧 8. Notifications & Calendar Integration
| Trigger | Type | Recipient | Message |
|----------|------|------------|----------|
| New visit added | WhatsApp | Assigned employee | Visit logged for [Lead Name] on [Date] |
| Follow-up due | Email | Assigned employee | Reminder: Follow-up scheduled for [Date] |
| Visit reviewed | Email | Manager/Admin | Visit [Title] was reviewed successfully |

Visits appear automatically in the CRM Calendar with **teal color coding** and can include clickable map locations.

---

## 📈 9. Reports & Exports
- Export visit records by date, employee, or lead
- Report visits pending follow-up
- Analyze visit-to-conversion ratios
- Summary report by visit outcomes

---

## ⏳ 10. Future Enhancements (Phase 2)
- GPS auto-route logging and live tracking
- Photo timestamp verification for authenticity
- Integration with attendance check-in/out module
- Voice-to-text summary for on-site reporting

---

This document defines the complete structure, relationships, and business logic of the **Visits Section** within the CRM module of Karyalay ERP.


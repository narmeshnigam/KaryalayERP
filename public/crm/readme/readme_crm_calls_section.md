**Karyalay ERP: CRM Calls Section – Functional Specification Document**

---

### ☎️ Module Name: CRM – Calls Section
The **Calls Section** in the CRM module is designed to record and manage telephonic interactions with potential or existing clients. These call logs help maintain communication history, schedule follow-ups, and provide insights into engagement effectiveness.

This section integrates directly with **Leads** and supports both inbound and outbound call tracking.

---

## 📆 1. Functional Overview
The Calls module enables users to:
- Log telephonic conversations related to leads
- Record the purpose, summary, duration, and outcomes of calls
- Plan and schedule follow-ups directly from call records
- Attach voice notes or screenshots as supporting files
- Automatically update the lead’s last contact timestamp

---

## ✨ 2. Feature List

### Employee Side:
- Add or edit call logs (linked to specific leads)
- Upload call summary, duration, and outcomes
- Set a follow-up date and type (optional)
- View upcoming scheduled calls in calendar

### Manager/Admin Side:
- View all call records across employees
- Filter calls by lead, employee, or date
- Export call summaries and follow-up reports
- Track employee responsiveness and communication frequency

---

## 🧮 3. Database Structure Reference (from Schema Doc)
**Table:** `crm_calls`

| Field          | Type            | Description                                         |
|----------------|-----------------|-----------------------------------------------------|
| id             | INT, PK, AI      | Unique call ID                                      |
| lead_id        | INT, FK NULL     | Reference to the related lead                       |
| title          | VARCHAR(150)     | Call topic or purpose                               |
| summary        | TEXT             | Call notes or discussion summary                    |
| call_date      | DATETIME         | Date and time when the call took place              |
| duration       | VARCHAR(20)      | Optional duration (e.g., 10m, 5m30s)                |
| outcome        | VARCHAR(100)     | Result (Interested, No Answer, Follow-Up, etc.)     |
| follow_up_date | DATE NULL        | Optional next planned contact                       |
| follow_up_type | ENUM NULL        | Type of next action (Call / Meeting / Visit / Task) |
| created_by     | INT, FK          | ID of employee who logged the call                  |
| assigned_to    | INT, FK          | Responsible employee ID                             |
| location       | TEXT             | Optional geo-location of caller                     |
| attachment     | TEXT             | Optional voice note or supporting document          |
| created_at     | TIMESTAMP        | Creation timestamp                                  |
| updated_at     | TIMESTAMP NULL   | Update timestamp                                   |

---

## 🔗 4. Relationship with Other CRM Modules
| Related Module | Relationship Type | Purpose |
|----------------|-------------------|----------|
| Leads | Many-to-One | Every call is associated with a specific lead |
| Tasks | Optional Link | Follow-up tasks can be generated from calls |
| Meetings | Optional Link | Calls can result in scheduling meetings |

Each call entry contributes to updating the `last_contacted_at` field in its associated lead.

---

## 🧱 5. Frontend Pages
| Page | URL Route | Description | Access Role |
|------|------------|-------------|--------------|
| All Calls | `/crm/calls` | View, filter, and export call records | Admin, Manager |
| My Calls | `/crm/calls/my` | Calls logged or assigned to current user | Employee |
| Add Call | `/crm/calls/add` | Log a new call with details and attachments | Employee, Manager |
| Edit Call | `/crm/calls/edit/:id` | Modify existing call record | Manager, Admin |
| Call Details | `/crm/calls/view/:id` | View single call entry with full summary | All roles |

---

## 🚀 6. Backend Routes (PHP Endpoints)
| Method | Route | Purpose | Auth |
|--------|--------|----------|-------|
| GET | `/api/crm/calls` | Fetch all call logs | Yes |
| GET | `/api/crm/calls/:id` | Fetch specific call record | Yes |
| POST | `/api/crm/calls/add` | Add a new call log | Yes |
| POST | `/api/crm/calls/update/:id` | Update call details | Yes |
| DELETE | `/api/crm/calls/delete/:id` | Soft delete call record | Admin |

---

## ⚙️ 7. Validations & Rules
- `lead_id` must exist in the `crm_leads` table
- `call_date` cannot be in the future
- `title` and `summary` are mandatory
- Follow-up type must match one of (Call, Meeting, Visit, Task)
- File attachments allowed: PDF, JPG, PNG, MP3 (max 3MB)

---

## 📧 8. Notifications & Calendar Integration
| Trigger | Type | Recipient | Message |
|----------|------|------------|----------|
| New call added | WhatsApp | Assigned employee | New call record added for [Lead Name] |
| Follow-up due | Email | Assigned employee | Reminder: Follow-up scheduled for [Date] |
| Missed follow-up | Email | Manager | Employee missed call follow-up for [Lead] |

All scheduled follow-ups from calls are auto-populated into the CRM Calendar under the orange label category.

---

## 📈 9. Reports & Exports
- Export all call records by date, employee, or outcome
- Filter by call duration or lead source
- Lead-wise or employee-wise call summary reports
- Follow-up pending or completed report

---

## ⏳ 10. Future Enhancements (Phase 2)
- Integration with telephony APIs (Twilio, Exotel, etc.)
- Auto-call logging for connected numbers
- AI-based sentiment analysis from voice recordings
- Missed call auto-tracking for inbound leads

---

This document specifies the complete functionality, integrations, and structure of the **Calls Section** within the CRM module for Karyalay ERP.


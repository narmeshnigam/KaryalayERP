**Karyalay ERP: CRM Meetings Section – Functional Specification Document**

---

### 🗓️ Module Name: CRM – Meetings Section
The **Meetings Section** in the CRM module manages scheduled meetings between company representatives and leads or clients. It helps teams organize, document, and follow up on in-person or virtual discussions.

This module integrates closely with **Leads**, **Visits**, and the shared CRM **Calendar** for unified visibility.

---

## 📆 1. Functional Overview
The Meetings module allows users to:
- Schedule, record, and manage meeting events linked to leads
- Capture agendas, notes, and outcomes for each meeting
- Add attachments such as PDFs, images, or meeting minutes
- Schedule follow-ups directly after the meeting
- Automatically reflect meetings in the unified CRM Calendar

---

## ✨ 2. Feature List

### Employee Side:
- Create and view meetings assigned to them
- Add meeting details, agenda, and post-meeting outcomes
- Upload documents or snapshots related to the discussion
- Schedule next steps (follow-up call, visit, or task)

### Manager/Admin Side:
- View and manage all meetings across teams
- Assign or reassign meetings to employees
- Export meeting records by date, employee, or lead
- Review outcomes for performance evaluation

---

## 🧮 3. Database Structure Reference (from Schema Doc)
**Table:** `crm_meetings`

| Field          | Type            | Description                                         |
|----------------|-----------------|-----------------------------------------------------|
| id             | INT, PK, AI      | Unique meeting ID                                   |
| lead_id        | INT, FK NULL     | Linked lead (optional)                              |
| title          | VARCHAR(150)     | Meeting subject/title                               |
| agenda         | TEXT             | Agenda or discussion points                         |
| meeting_date   | DATETIME         | Scheduled date/time of the meeting                  |
| outcome        | TEXT NULL        | Meeting summary, decisions, or notes                |
| follow_up_date | DATE NULL        | Optional next follow-up date                        |
| follow_up_type | ENUM NULL        | Call / Meeting / Visit / Task                       |
| created_by     | INT, FK          | Employee who created the record                     |
| assigned_to    | INT, FK          | Responsible employee                                |
| location       | TEXT             | Meeting location or meeting link                    |
| attachment     | TEXT             | Optional attached file                              |
| created_at     | TIMESTAMP        | Creation timestamp                                  |
| updated_at     | TIMESTAMP NULL   | Last update timestamp                               |

---

## 🔗 4. Relationship with Other CRM Modules
| Related Module | Relationship Type | Purpose |
|----------------|-------------------|----------|
| Leads | Many-to-One | Each meeting can be tied to a lead for continuity |
| Visits | Optional Link | Physical meetings may create visit records |
| Tasks | Optional Link | Follow-up tasks can be generated post-meeting |

---

## 🧱 5. Frontend Pages
| Page | URL Route | Description | Access Role |
|------|------------|-------------|--------------|
| All Meetings | `/crm/meetings` | View and manage all meeting records | Admin, Manager |
| My Meetings | `/crm/meetings/my` | List of meetings assigned to current user | Employee |
| Add Meeting | `/crm/meetings/add` | Create new meeting entry | Employee, Manager |
| Edit Meeting | `/crm/meetings/edit/:id` | Modify existing meeting | Manager, Admin |
| Meeting Details | `/crm/meetings/view/:id` | View full meeting info and notes | All roles |

---

## 🚀 6. Backend Routes (PHP Endpoints)
| Method | Route | Purpose | Auth |
|--------|--------|----------|-------|
| GET | `/api/crm/meetings` | Fetch all meeting records | Yes |
| GET | `/api/crm/meetings/:id` | Fetch a specific meeting record | Yes |
| POST | `/api/crm/meetings/add` | Add a new meeting record | Yes |
| POST | `/api/crm/meetings/update/:id` | Update an existing meeting | Yes |
| DELETE | `/api/crm/meetings/delete/:id` | Soft delete meeting record | Admin |

---

## ⚙️ 7. Validations & Rules
- Meeting date/time must be in the present or future
- `lead_id` must exist if linked to a lead
- Title and agenda are mandatory
- Follow-up type must be one of (Call, Meeting, Visit, Task)
- File attachments allowed: PDF, JPG, PNG (max 3MB)

---

## 📧 8. Notifications & Calendar Integration
| Trigger | Type | Recipient | Message |
|----------|------|------------|----------|
| New meeting scheduled | WhatsApp | Assigned employee | You have a meeting scheduled for [Date] |
| Follow-up due | Email | Assigned employee | Reminder: Follow-up after meeting on [Date] |
| Meeting updated | Email | Admin/Manager | Meeting [Title] has been modified by [User] |

Meetings appear automatically in the CRM Calendar with **green color coding**. Clicking an event opens its details popup.

---

## 📈 9. Reports & Exports
- Export meeting records by date, employee, or lead
- Track completed vs. pending meetings
- Follow-up status reports per employee
- Summary report by meeting outcomes

---

## ⏳ 10. Future Enhancements (Phase 2)
- Video call integration (Zoom, Google Meet, Teams)
- Smart scheduling with calendar conflict detection
- Meeting notes template and minutes upload automation
- AI-generated meeting summaries

---

This document defines the complete structure, integration, and logic of the **Meetings Section** within the CRM module of Karyalay ERP.


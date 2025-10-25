**Karyalay ERP: CRM Module – Database Schema Document (Revised for Lead-Centric Follow-Up)**

---

### 🔍 Purpose
This document defines all database tables under the CRM module of **Karyalay ERP – Phase 1**, now updated with a **lead-centric follow-up model**. The design ensures easy scalability and logical consistency across all CRM entities: Leads, Calls, Meetings, Visits, and Tasks.

Each table supports SME-oriented features such as simplified follow-up scheduling, multi-item product/service interests, and relationship mapping between activities and leads.

---

## 📄 Common Fields Across Tables
These fields exist in **all CRM tables** for uniformity and data integrity.

| Field             | Type           | Description                                             |
|------------------|----------------|---------------------------------------------------------|
| id               | INT, PK, AI    | Primary Key                                             |
| lead_id          | INT, FK NULL   | Optional reference to the linked lead                   |
| created_by       | INT, FK        | Employee ID who created the record                      |
| assigned_to      | INT, FK        | Employee responsible for the action                     |
| location         | TEXT           | Optional GPS or address                                 |
| attachment       | TEXT           | Optional file path (PDF/Image/Audio)                    |
| created_at       | TIMESTAMP      | Record creation timestamp                               |
| updated_at       | TIMESTAMP NULL | Auto-updated on modification                            |

---

## 📊 1. `crm_leads`
Holds primary client and opportunity data. Follow-ups and related activities are attached to each lead.

| Field              | Type            | Description                                                |
|--------------------|-----------------|------------------------------------------------------------|
| name               | VARCHAR(100)    | Contact person name                                        |
| company_name       | VARCHAR(150)    | Optional organization name                                 |
| phone              | VARCHAR(20)     | Mobile number                                              |
| email              | VARCHAR(100)    | Email address                                              |
| source             | VARCHAR(50)     | Web, Referral, Walk-in, etc.                               |
| status             | ENUM            | New / Contacted / Converted / Dropped                      |
| notes              | TEXT            | Optional remarks                                           |
| interests          | TEXT            | Comma-separated product/service tags (multi-select)        |
| follow_up_date     | DATE NULL       | Next follow-up date                                        |
| follow_up_type     | ENUM NULL       | Type of follow-up: Call / Meeting / Visit / Task           |
| follow_up_created  | BOOLEAN DEFAULT 0 | Whether a linked follow-up activity was auto-generated   |
| last_contacted_at  | DATETIME NULL   | Timestamp of last CRM activity (call/visit/meeting/task)   |

---

## 📊 2. `crm_tasks`
Records task-level actions that may or may not relate to a lead.

| Field             | Type            | Description                                                |
|-------------------|-----------------|------------------------------------------------------------|
| title             | VARCHAR(150)    | Task title                                                 |
| description       | TEXT            | Task content/details                                       |
| status            | ENUM            | Pending / In Progress / Completed                          |
| due_date          | DATE            | Deadline for task completion                               |
| completion_notes  | TEXT NULL       | Notes added upon completion                                |
| completed_at      | DATETIME NULL   | When the task was marked done                              |
| closed_by         | INT, FK NULL    | Employee ID who marked completion                          |

---

## 📊 3. `crm_calls`
Tracks telephonic conversations related to a lead or general operations.

| Field          | Type            | Description                                   |
|----------------|-----------------|-----------------------------------------------|
| title          | VARCHAR(150)    | Call topic                                    |
| summary        | TEXT            | Notes from the call                           |
| call_date      | DATETIME        | When the call occurred                        |
| duration       | VARCHAR(20)     | Optional duration (e.g., 10m, 5m30s)          |
| outcome        | VARCHAR(100)    | Call outcome (Interested, No Answer, Follow-Up) |

---

## 📊 4. `crm_meetings`
Stores details of client or internal meetings, optionally linked to a lead.

| Field          | Type            | Description                                   |
|----------------|-----------------|-----------------------------------------------|
| title          | VARCHAR(150)    | Meeting subject                               |
| agenda         | TEXT            | Key discussion points                         |
| meeting_date   | DATETIME        | Date/time of meeting                          |
| outcome        | TEXT NULL       | Meeting summary or conclusion                 |

---

## 📊 5. `crm_visits`
Logs field or on-site visits with geolocation and notes.

| Field          | Type            | Description                                   |
|----------------|-----------------|-----------------------------------------------|
| title          | VARCHAR(150)    | Visit purpose                                 |
| notes          | TEXT            | Visit details and observations                |
| visit_date     | DATETIME        | Date/time of visit                            |
| outcome        | TEXT NULL       | Optional visit result or remarks              |

---

## ⛓️ Foreign Key References
| Field | References | Description |
|--------|-------------|--------------|
| `lead_id` | `crm_leads.id` | Connects activity with lead (optional) |
| `created_by`, `assigned_to`, `closed_by` | `employees.id` | Employee relation mapping |

---

## ⚙️ Data Flow Example
**1. Lead Creation:** `crm_leads` record created → may include `follow_up_date` and `follow_up_type`.
**2. Auto-Scheduler:** On reaching `follow_up_date`, a new entry is created in one of the activity tables based on `follow_up_type`.
**3. Activity Logging:** Every new Call, Meeting, Visit, or Task updates `last_contacted_at` in the linked lead.
**4. Conversion:** When status = 'Converted', all pending follow-ups are ignored.

---

## 📈 Standardization Notes
- All date/time fields use MySQL standard `DATETIME` (UTC timezone)
- Attachments stored as relative paths in `/uploads/crm/`
- `status` ENUMs are configurable per module from admin settings
- Lead follow-up logic is central; activity follow-ups are optional extensions

---

This schema provides the foundation for a **lead-driven CRM flow** within Karyalay ERP — lightweight, relationally consistent, and ready for expansion into client and project management in Phase 2.


**Karyalay ERP: CRM Leads Section – Functional Specification Document**

---

### 🧩 Module Name: CRM – Leads Section
The **Leads Section** acts as the central component of the CRM system in Karyalay ERP. It is responsible for capturing potential customers, managing their product/service interests, planning follow-ups, and linking associated CRM activities (calls, meetings, visits, tasks).

This section serves as the **origin point** for all future CRM actions.

---

## 📆 1. Functional Overview
The Leads module allows users to:
- Record new leads manually or via integrated sources (form/API)
- Define lead details, interest areas, and responsible employee
- Set a follow-up date and preferred follow-up type (Call/Meeting/Visit/Task)
- Track current status (New, Contacted, Converted, Dropped)
- View and update follow-up activities linked to the lead
- Export or review the lead’s timeline of interactions

---

## ✨ 2. Feature List

### Employee Side:
- Add new leads with contact details and interest areas
- View assigned leads and update status
- Mark leads as contacted, converted, or dropped
- Set next follow-up and track reminders in calendar

### Manager/Admin Side:
- View all leads with filters (status, employee, date)
- Assign/reassign leads to employees
- Manage lead statuses and interest categories
- Export lead and follow-up data

---

## 🧮 3. Database Structure Reference (from Schema Doc)
**Table:** `crm_leads`

| Field              | Type            | Description                                                |
|--------------------|-----------------|------------------------------------------------------------|
| id                 | INT, PK, AI      | Unique Lead ID                                             |
| name               | VARCHAR(100)     | Lead or contact name                                       |
| company_name       | VARCHAR(150)     | Optional organization name                                 |
| phone              | VARCHAR(20)      | Contact number                                             |
| email              | VARCHAR(100)     | Email address                                              |
| source             | VARCHAR(50)      | Web, Referral, Walk-in, etc.                               |
| status             | ENUM             | New / Contacted / Converted / Dropped                      |
| notes              | TEXT             | General remarks                                            |
| interests          | TEXT             | Comma-separated product/service tags (multi)               |
| follow_up_date     | DATE NULL        | Next scheduled follow-up date                              |
| follow_up_type     | ENUM NULL        | Call / Meeting / Visit / Task                              |
| follow_up_created  | BOOLEAN DEFAULT 0 | Whether a follow-up activity was auto-generated            |
| last_contacted_at  | DATETIME NULL    | Timestamp of last linked activity                          |
| created_by         | INT, FK          | Employee ID who created the record                         |
| assigned_to        | INT, FK          | Responsible employee ID                                    |
| created_at         | TIMESTAMP        | Creation timestamp                                         |
| updated_at         | TIMESTAMP NULL   | Update timestamp                                           |

---

## 🧭 4. Lead Lifecycle Flow

### Step 1 – Creation
- A lead is added with required details and optional product/service interests.
- Assigned to an employee for handling.

### Step 2 – Engagement
- The employee performs activities (calls, meetings, visits, or tasks) linked to the lead.
- The system updates `last_contacted_at` automatically on each new related activity.

### Step 3 – Follow-Up Management
- User may set a follow-up date and type.
- When the follow-up date arrives, the system auto-creates a corresponding CRM entry (based on follow-up type) and places it in the shared calendar.

### Step 4 – Conversion or Closure
- Once the lead is marked as **Converted**, all pending follow-ups are ignored.
- Dropped leads are retained for record but excluded from reminders.

---

## 🔗 5. Integration with Other CRM Sections
| Related Module | Relationship Type | Purpose |
|----------------|-------------------|----------|
| Calls | One-to-Many | Every call log can link to a lead for traceability |
| Meetings | One-to-Many | Each meeting belongs to a lead for progress tracking |
| Visits | One-to-Many | Visits are tied to a lead for client engagement records |
| Tasks | One-to-Many | Tasks are created against leads for internal actions |

---

## 🧱 6. Frontend Pages
| Page | URL Route | Description | Access Role |
|------|------------|-------------|--------------|
| All Leads | `/crm/leads` | View, search, and filter leads | Admin, Manager |
| My Leads | `/crm/leads/my` | View leads assigned to logged-in user | Employee |
| Add Lead | `/crm/leads/add` | Add new lead entry | Employee, Manager |
| Lead Details | `/crm/leads/view/:id` | View lead info + linked activities | All roles |
| Edit Lead | `/crm/leads/edit/:id` | Update details, assign, or change status | Manager, Admin |

---

## 🚀 7. Backend Routes (PHP Endpoints)
| Method | Route | Purpose | Auth |
|--------|--------|----------|-------|
| GET | `/api/crm/leads` | Fetch all leads | Yes |
| GET | `/api/crm/leads/:id` | Fetch specific lead details | Yes |
| POST | `/api/crm/leads/add` | Add new lead | Yes |
| POST | `/api/crm/leads/update/:id` | Edit existing lead info | Yes |
| DELETE | `/api/crm/leads/delete/:id` | Soft delete a lead | Admin |

---

## ⚙️ 8. Validations & Rules
- Phone or email must be unique per lead
- Follow-up date cannot be in the past
- Status transitions are sequential (New → Contacted → Converted/Dropped)
- Follow-up type must be one of (Call, Meeting, Visit, Task)
- Mandatory fields: Name, Assigned Employee, Source

---

## 📧 9. Email / WhatsApp Notifications
| Trigger | Type | Recipient | Message |
|----------|------|------------|----------|
| New lead assigned | WhatsApp | Employee | You’ve been assigned a new lead: [Lead Name] |
| Follow-up due | Email | Assigned employee | Reminder: Follow-up scheduled for [Date] |
| Lead converted | Email | Admin/Manager | Lead [Lead Name] has been marked as Converted |

---

## 📈 10. Reports & Exports
- Export all leads by status/date/employee
- Conversion rate reports by employee
- Upcoming follow-ups list
- Filter by interest or source

---

## ⏳ 11. Future Enhancements (Phase 2)
- Integration with web lead forms & campaign tracking
- Lead scoring mechanism based on interactions
- Lead-to-client conversion pipeline
- AI-based follow-up suggestions

---

This document outlines the complete functional, structural, and integration details of the **Leads Section** within the CRM module for Karyalay ERP.


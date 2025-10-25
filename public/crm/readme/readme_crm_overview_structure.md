**Karyalay ERP: CRM Module – Structure & Relation Overview (Revised)**

---

### 📆 Module Scope
The CRM module in Phase‑1 of **Karyalay ERP** is designed to offer a compact yet complete activity‑tracking system tailored for SMEs. It enables teams to manage business interactions, employee assignments, and sales follow‑ups in one place.

#### Core Sections:
1. **Leads** – Captures potential customers and their product/service interests.
2. **Calls** – Records telephonic interactions and follow‑ups.
3. **Meetings** – Logs in‑person or online discussions.
4. **Visits** – Tracks on‑site visits or field meetings.
5. **Tasks** – Represents actionable work items assigned to employees.

Each section is self‑contained but connected through unified employee assignment, status tracking, and a shared calendar view.

---

### 🧩 Conceptual Overview
The CRM module is designed with a *lead‑centric follow‑up model*, supported by cross‑linked activities.

#### 🔹 Key Principles
- Every **lead** can have multiple related activities (calls, meetings, visits, or tasks).
- Activities are **manually entered** by users to keep flexibility high for SMEs.
- Follow‑up dates are defined primarily at the **lead level** to simplify tracking.
- Each activity may optionally have its own follow‑up date (for time‑sensitive workflows), but the system treats lead‑level follow‑ups as the master reference.

---

### 🔁 Shared Concepts Across All Sections
| Concept             | Description                                                                 |
|---------------------|-----------------------------------------------------------------------------|
| **Assigned To**     | All entries are linked to employees for ownership tracking                 |
| **Created By**      | Tracks the user who added the record                                        |
| **Location**        | Optional GPS or address entry captured at creation                         |
| **Attachment**      | Optional file/image/voice note (max 3 MB)                                  |
| **Status**          | Dynamic ENUMs per activity (Pending, In Progress, Completed, etc.)          |
| **Timestamps**      | Includes `created_at`, `updated_at`, and completion timestamps              |
| **Completion Notes**| Available for Tasks (records how it was closed)                            |
| **Follow‑Up Date (Lead)** | Primary follow‑up indicator at lead level for next planned engagement  |
| **Follow‑Up Type**  | Defines next step (Call / Meeting / Visit / Task) when scheduling follow‑up |

---

### 🔗 Module Relationships
| From Module | Related Module(s) | Relation Type | Description |
|--------------|-------------------|----------------|--------------|
| **Leads** | Calls, Meetings, Visits, Tasks | One‑to‑many | A single lead can generate multiple follow‑up activities. |
| **Calls** | Leads | Many‑to‑one | Calls are logged against leads for traceability. |
| **Meetings** | Leads | Many‑to‑one | Meetings are logged as lead‑level activities. |
| **Visits** | Leads | Many‑to‑one | Visits belong to a lead for tracking client interactions. |
| **Tasks** | Leads | Many‑to‑one | Tasks are internal actions assigned toward a lead. |

📘 *Note:* These links are logical in Phase‑1 (via `lead_id` references) but not enforced by foreign key constraints to allow independent module use.

---

### 📅 Calendar Integration
The CRM calendar provides a unified view of all dated entries (tasks, meetings, calls, visits) plus lead‑level follow‑ups.

| Calendar Entry Type | Display | Color | Notes |
|----------------------|----------|--------|--------|
| Tasks | ✔️ | Blue | Shows due date and completion status |
| Meetings | ✔️ | Green | Displays date/time and linked lead name |
| Calls | ✔️ | Orange | Displays call schedule and summary tooltip |
| Visits | ✔️ | Teal | Displays visit location (click opens map) |
| Lead Follow‑Ups | ✔️ | Purple | Auto‑generated calendar event for next planned action |

All events are interactive — clicking any entry opens a detailed view or the corresponding module form.

---

### 🔐 Role‑Based Access Matrix
| Role | Create | View Assigned | View All | Edit | Export | Delete |
|------|----------|----------------|-----------|-------|---------|----------|
| **Employee** | Yes (own) | Yes | No | Limited (status/notes) | No | No |
| **Manager** | Yes | Yes | Yes | Yes | Yes | No |
| **Admin** | Yes | Yes | Yes | Yes | Yes | Yes |

All role permissions are enforced consistently through backend and frontend layers. Managers can view subordinate activities; Admins have global visibility.

---

### 🧭 Follow‑Up Flow (Lead‑Centric System)
1. **When a new Lead is added:**
   - User can specify *interest items* (products/services).
   - Optionally define a *follow‑up date* and *type* (Call, Meeting, Visit, or Task).
2. **On reaching follow‑up date:**
   - System auto‑prompts or pre‑creates a corresponding CRM entry of selected type.
   - The event is shown in the unified calendar.
3. **Follow‑Up Chain:**
   - Each subsequent activity can update the next follow‑up date for continuity.
   - When a lead is marked as *Converted* or *Dropped*, future follow‑ups are disabled.

This approach avoids clutter while ensuring traceable engagement cycles for SMEs.

---

### 📊 Reporting & Logs
All CRM subsections (Leads, Calls, Meetings, Visits, Tasks) support:
- Date‑range and employee‑wise filters
- Status‑based segmentation
- Export to CSV / Excel / PDF
- Inclusion of lead‑level follow‑up schedules in reports

#### Audit & Logs (Phase‑2)
- Track who created, edited, or closed an activity
- Timeline view of activities linked to each lead

---

### 🧱 Future Expansion (Phase‑2)
- Add *Clients/Companies* table to group leads
- Convert leads directly to clients post‑conversion
- Connect CRM to *Projects* module for delivery tracking
- Smart follow‑up engine (AI‑based scheduling suggestions)
- Analytics Dashboard integration for conversion KPIs

---

### ✅ Summary
This updated CRM structure prioritizes a **lead‑centric, follow‑up‑enabled workflow** rather than per‑activity reminders. It balances simplicity for SMEs with scalability for structured tracking. Each section (Leads, Calls, Meetings, Visits, Tasks) operates independently but contributes to the same relationship cycle — *from lead acquisition to engagement and closure*.


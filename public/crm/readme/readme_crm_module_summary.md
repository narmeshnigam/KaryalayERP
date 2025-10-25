**Karyalay ERP: CRM Module – Comprehensive Summary Document**

---

### 🧭 Module Overview
The **Customer Relationship Management (CRM)** module in **Karyalay ERP** is designed as a compact yet powerful suite for **Small and Medium Enterprises (SMEs)** to manage customer interactions, track business opportunities, and maintain follow-up discipline without complexity. It provides visibility into every stage of lead handling — from acquisition to engagement to closure — all under a single interface.

The CRM module is divided into five interconnected sections:
1. **Leads** – The source and core of all CRM activities.
2. **Calls** – For managing telephonic interactions.
3. **Meetings** – For documenting in-person or virtual discussions.
4. **Visits** – For tracking field interactions and client site visits.
5. **Tasks** – For managing actionable items and ensuring accountability.

---

### 🔗 Module Connectivity
The CRM sections are interdependent and operate around a **lead-centric follow-up model**. Each section supports both standalone use and relational integration.

| Source Module | Linked Modules | Relationship Type | Description |
|----------------|----------------|------------------|--------------|
| **Leads** | Calls, Meetings, Visits, Tasks | One-to-Many | Each lead can generate multiple follow-up activities. |
| **Calls** | Leads, Meetings, Tasks | Many-to-One / Optional | Calls may result in scheduling a meeting or task. |
| **Meetings** | Leads, Visits, Tasks | Many-to-One / Optional | Meetings may lead to field visits or post-meeting tasks. |
| **Visits** | Leads, Tasks | Many-to-One / Optional | Field visits can produce follow-up tasks or notes. |
| **Tasks** | Leads, Calls, Meetings, Visits | Many-to-One / Optional | Tasks close the action loop for activities originating from any module. |

Each activity (Call, Meeting, Visit, Task) updates the **lead’s last_contacted_at** field for real-time insight into communication history.

---

### 📅 Calendar Integration
The CRM Calendar provides a unified timeline of all scheduled and completed activities. It helps users plan daily actions and view upcoming or overdue engagements.

| Activity Type | Calendar Color | Description |
|----------------|----------------|--------------|
| **Lead Follow-up** | Purple | Next scheduled interaction with a lead |
| **Call** | Orange | Scheduled or completed telephonic activity |
| **Meeting** | Green | In-person or online discussion |
| **Visit** | Teal | Field meeting or on-site interaction |
| **Task** | Blue | Assigned or completed operational task |

Each event is clickable, opening detailed records and linked entities. Follow-up events auto-populate the calendar upon creation.

---

### 🔄 Workflow Summary
1. **Lead Entry:**
   - Created manually or through integrated sources.
   - Includes product/service interests and responsible employee assignment.
   - Optionally includes follow-up date and type (Call, Meeting, Visit, Task).

2. **Activity Logging:**
   - Employees record interactions under Calls, Meetings, Visits, or Tasks.
   - Each record optionally links to a lead and auto-updates its last contact timestamp.

3. **Follow-up Creation:**
   - Follow-up can be scheduled from any activity or directly at the lead level.
   - On reaching the follow-up date, the system auto-creates a new CRM activity and calendar entry.

4. **Lead Conversion:**
   - When marked as Converted, future follow-ups are disabled.
   - Dropped leads remain archived for recordkeeping.

---

### 🧱 Shared Functional Concepts
- **Assignments:** Each record includes `created_by` and `assigned_to` fields.
- **Geo-Tracking:** All activity modules support optional location capture.
- **Attachments:** Files, photos, and documents can be attached for reference.
- **Follow-up Options:** All sections include optional fields for `follow_up_date` and `follow_up_type`.
- **Audit Trails:** Creation and modification timestamps are auto-maintained.

---

### ⚙️ Access Control Matrix
| Role | Access Scope | Key Permissions |
|------|---------------|----------------|
| **Employee** | Own records | Add, edit (own), mark complete, schedule follow-up |
| **Manager** | Team records | Assign, reassign, edit, export, approve completion |
| **Admin** | Global | Full access, including delete and configuration |

---

### 📧 Communication & Notifications
Automated notifications via **Email** and **WhatsApp** ensure timely updates and accountability.

| Trigger | Channel | Recipient | Example Message |
|----------|----------|------------|----------------|
| New Lead Assigned | WhatsApp | Employee | “You’ve been assigned a new lead: [Lead Name]” |
| Follow-up Due | Email | Employee | “Reminder: Follow-up scheduled for [Date]” |
| Task Completed | Email | Creator | “Task [Task Title] marked as completed by [Employee]” |
| Meeting Scheduled | WhatsApp | Employee | “You have a meeting scheduled on [Date/Time]” |

---

### 📊 Reporting & Exports
Each CRM section supports filtered exports and aggregated reporting.

**Common Reporting Options:**
- Filter by Employee, Date, or Lead
- Status summaries (Pending, Completed, Dropped)
- Conversion and engagement tracking
- Follow-up compliance reports
- Export formats: CSV, Excel, PDF

---

### 🧮 Database Consistency Highlights
All CRM tables share a standardized structure:
- Unified timestamping (`created_at`, `updated_at`)
- Common relationship fields (`lead_id`, `created_by`, `assigned_to`)
- Optional attachments for verification or documentation
- ENUM-based status and follow-up type consistency

---

### 📈 Managerial Insights
Managers can:
- View lead funnel (New → Contacted → Converted/Dropped)
- Monitor team performance (Calls, Meetings, Visits, Tasks)
- Track overdue follow-ups
- Generate reports on productivity and responsiveness

---

### 🧠 SME-Focused Design Philosophy
The CRM module avoids the complexities of enterprise-grade CRMs and instead emphasizes:
- Simplicity in data entry (manual + intuitive)
- Quick activity linking to avoid redundant forms
- Built-in follow-up system tied to real business actions
- Offline-compatible structure (usable in local setups)
- Scalability to integrate with Projects and Clients in future phases

---

### 🚀 Future Phase Integrations
In Phase 2, the CRM module will extend to include:
1. **Clients/Companies Module** – Convert leads into client entities.
2. **Projects Integration** – Associate CRM activities with project milestones.
3. **Analytics Dashboard** – Visual KPIs (conversion rates, lead heatmaps, employee performance).
4. **Smart Scheduler** – AI-based follow-up recommendations.
5. **Automation Layer** – Auto-create tasks or calls from predefined templates.

---

### ✅ Summary
The **Karyalay ERP CRM Module** acts as the bridge between **sales engagement and organizational execution**, ensuring every lead is followed, every visit logged, and every task completed with accountability. It is built for SMEs — lightweight, efficient, and practical — delivering structure without complexity.

**Core CRM Sections:** Leads • Calls • Meetings • Visits • Tasks

**Primary Strength:** Lead-centric, follow-up-driven workflow ensuring no opportunity is missed and every customer interaction is tracked end-to-end.


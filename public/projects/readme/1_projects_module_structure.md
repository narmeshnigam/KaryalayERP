**Karyalay ERP: Projects Module – Relation & Structure Document**

---

### 🧭 Module Overview
The **Projects Module** in Karyalay ERP serves as a central workspace for managing both **internal** and **client-linked** projects. It is designed to streamline planning, execution, and collaboration, while integrating deeply with existing ERP modules such as **Clients**, **Contacts**, **Notebook**, and **Document Vault**.

The system follows a modular hierarchy:

> **Project → Phases (optional) → Tasks → Members/Documents/Activity**

Each layer maintains independent control yet inherits contextual information from its parent, ensuring traceability, accountability, and ease of management.

---

## ⚙️ 1. Core Relationships

| Parent Entity | Child Entity | Description |
|----------------|--------------|--------------|
| **Project** | **Phase** | Optional grouping for tasks, milestones, or sprints |
| **Phase** | **Task** | Tasks may belong to a phase or directly under project |
| **Project** | **Member** | Defines assigned employees and their roles |
| **Project** | **Document** | Linked files related to the project (Vault integration) |
| **Project** | **Activity Log** | Tracks all changes, actions, and uploads |

---

## 🔗 2. External Integrations
| Module | Type of Integration | Description |
|---------|----------------------|--------------|
| **Clients** | Downstream | A client-linked project stores `client_id` to maintain continuity from CRM → Client → Project |
| **Contacts** | Lateral | Project contacts may be added via Contacts Module (e.g., client coordinators, vendors) |
| **Notebook** | Contextual | Notes (like minutes, SOPs, reports) can be linked to projects using `linked_entity_type = 'Project'` |
| **Document Vault** | Deep | All project attachments (contracts, drawings, reports) stored using Vault reference paths |
| **Users / Employees** | Functional | Team assignments, project ownership, and task allocation |
| **CRM** | Upstream | Conversion path from lead → client → project preserves traceability |

---

## 🧱 3. Entity Hierarchy & Key Attributes

### **A. Project (Root Entity)**
Represents an initiative or assignment being executed by one or more users.

**Attributes include:**
- Project title, type (internal/client-linked), status, priority, owner, start/end dates, and progress.
- Linked client (if applicable), tags, and description.
- Overall completion calculated from task or phase progress.

**Lifecycle:** Draft → Active → Completed → Archived.

---

### **B. Phases (Optional Layer)**
Used to divide a project into structured segments or milestones.

**Attributes:** title, start/end date, progress, and current status.
- Each phase aggregates the progress of its linked tasks.
- If not used, tasks are managed directly under the project.

---

### **C. Tasks (Operational Layer)**
Smallest actionable unit under projects.

**Attributes:** title, description, due date, priority, progress %, and status.
- Supports multiple assignees.
- Allows detailed closing notes.
- Task completion auto-updates project/phase progress.
- Tasks can be imported from templates or created manually.

**Lifecycle:** Pending → In Progress → Review → Completed.

---

### **D. Members (Team Association)**
Represents users (employees or admins) involved in a project.

**Attributes:** user_id, role (Owner/Contributor/Viewer), assigned_date.
- Used to define access level and notification scope.
- Member roles dictate permissions within the project workspace.

---

### **E. Documents (File Association)**
Linked files stored in the Document Vault or under `/uploads/projects/`.

**Attributes:** file name, path, uploaded_by, doc_type, and timestamp.
- Supports version control via Vault references.
- Attachments visible in project’s Documents tab.

---

### **F. Activity Log (Audit Trail)**
Tracks every user action on the project for transparency and accountability.

**Attributes:** activity_type, description, performed_by, date_time.
- Captures task creation, status change, phase completion, document uploads, and comments.
- Aggregated into timeline view on project page.

---

## 🧩 4. Inter-Entity Behavior

1. **Task Progress → Phase Progress → Project Progress**  
   Each task contributes to its parent’s completion percentage.

2. **Ownership Propagation**  
   The project owner automatically becomes the default phase and task approver.

3. **Visibility Inheritance**  
   Members assigned at project level can automatically view all phases, tasks, and documents under that project.

4. **Role-Based Access Control**  
   - Owners: full access to edit/manage.
   - Contributors: task-level edit.
   - Viewers: read-only.

5. **Client Association**  
   When a project is linked to a client, all client contacts auto-suggest in the project’s contact dropdown.

---

## 🔐 5. Roles & Permissions Summary
| Role | Access Scope |
|------|---------------|
| **Admin** | Full CRUD access for all projects and sections |
| **Manager** | Manage own department/client projects |
| **Employee** | Access and edit assigned projects/tasks only |
| **Client (Future)** | View-only access to assigned projects via portal |

Unauthorized access → redirect to `/unauthorized`.

---

## 📊 6. Reporting & Dashboard Integration
- Project-level summary integrated into ERP Dashboard (KPIs: active projects, delayed, completed %).
- Drill-down to per-client or per-owner analytics.
- CSV/PDF exports for reports.
- Activity feed widgets for quick updates.

---

## ⏳ 7. Future Enhancements (Phase 2)
- Gantt Chart & timeline visualization.
- Task dependencies and critical path analysis.
- Expense/time tracking per project.
- Automated project health scoring.
- Client portal view with restricted document access.

---

### ✅ Summary
The **Projects Module** defines a flexible structure that allows SMEs to plan, execute, and monitor internal and client-based projects effectively. With a clean hierarchy (Project → Phase → Task) and deep ERP integrations, it will serve as the operational backbone for team collaboration, documentation, and client delivery within Karyalay ERP.


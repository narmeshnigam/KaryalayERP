**Karyalay ERP: Projects Module – Master Summary Document**

---

### 🧩 Module Overview
The **Projects Module** in **Karyalay ERP** is a comprehensive system for managing client and internal projects. It integrates project planning, task management, document storage, collaboration, and activity tracking in a single workspace. The module links tightly with **Clients**, **Users**, **Contacts**, **Notebook**, and **Document Vault**, ensuring a unified workflow across the ERP.

---

## ⚙️ 1) Core Components
| Section | Purpose |
|----------|----------|
| **Projects Overview** | Central hub for each project, showing KPIs, progress, members, documents, and recent activity. |
| **Phases** | Break down projects into milestones for structured execution and tracking. |
| **Tasks** | Manage individual actionable items with multiple assignees and progress updates. |
| **Members** | Define project-level team structure and access roles. |
| **Documents** | Store, version, and manage project-related files with Vault integration. |
| **Activity Log** | Chronological audit of all project-related actions and updates. |

---

## 🧮 2) Database Overview
Core tables used across the Projects module:

- `projects` – master record of all projects.
- `project_phases` – milestones or stages of a project.
- `project_tasks` – task records linked to projects or phases.
- `project_task_assignees` – mapping of tasks to assigned users.
- `project_members` – records of users with project-specific roles.
- `project_documents` – documents associated with a project.
- `project_templates` & `project_template_tasks` – reusable task templates.
- `project_activity_log` – audit trail of all project actions.

Each entity is tightly linked via `project_id` and contributes to overall project tracking and analytics.

---

## 📊 3) Project Lifecycle
1. **Creation** – A new project is created (Internal or Client-linked).
2. **Planning** – Phases are added, and members are assigned.
3. **Execution** – Tasks are created, assigned, and tracked.
4. **Collaboration** – Files and notes are shared; activity is continuously logged.
5. **Monitoring** – Dashboard and logs show progress, KPIs, and updates.
6. **Closure** – Project marked completed, with exportable summary and audit log.

---

## 🔗 4) Module Interconnections
| Module | Integration Description |
|---------|--------------------------|
| **Clients** | Client-linked projects created directly from CRM-converted leads. |
| **Contacts** | Contacts associated with clients appear as project references. |
| **Notebook** | Notes attached to projects or individual phases/tasks. |
| **Document Vault** | All files stored in Vault with project references. |
| **Users & Roles** | Access restricted by assigned project role. |
| **Dashboard** | Displays aggregated project data and KPIs. |

---

## 🔐 5) Access & Permissions Summary
| Role | View | Edit | Task Ops | Member Ops | Document Ops | Reports |
|------|------|------|-----------|-------------|---------------|----------|
| **Admin** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| **Manager** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| **Owner (Project)** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| **Contributor** | ✔ | ✖ (own tasks only) | ✔ (assigned) | ✖ | ✔ (upload) | ✖ |
| **Viewer** | ✔ | ✖ | ✖ | ✖ | ✖ | ✖ |

Unauthorized users attempting restricted actions are redirected to `/unauthorized`.

---

## 🧠 6) Reporting & Analytics
The Projects Module supports the following reporting layers:
- **Project Summary Reports** (Progress, Timeline, Phases, Tasks, Members)
- **Document Reports** (by type, uploader, or date)
- **Activity Reports** (full audit trail)
- **Performance Reports** (overdue tasks, member productivity)

Exports supported in CSV, XLSX, and PDF.

---

## 🔔 7) Notifications Overview
| Trigger | Recipient | Notification Type |
|----------|------------|------------------|
| Task Assigned | Assignee | Email & WhatsApp |
| Task Completed | Project Owner | Email |
| Document Uploaded | Members | Email & In-app alert |
| Phase Status Changed | Members | Email |
| Member Added | User | Email & WhatsApp |
| Project Status Updated | All Members | Email |

---

## 🧩 8) Automation & Derived Metrics
| Metric | Calculation |
|---------|--------------|
| **Project Progress** | Avg(phase.progress) or avg(task.progress) if no phases. |
| **Phase Progress** | Avg(task.progress) where `phase_id` = current. |
| **Overdue Tasks** | Count where due_date < today and status != Completed. |
| **Timeline %** | (Elapsed days / Total days) × 100. |

Automatic recalculations triggered on task/phase updates or daily scheduler.

---

## 🧠 9) Future Enhancements
- **Gantt Chart View** for project timeline visualization.
- **Time Tracking** for task-level effort logging.
- **Budget Tracking** and cost breakdown per project.
- **Dependency Mapping** between tasks/phases.
- **Client Portal** to view project progress externally.
- **AI Summaries** for auto-generated project status reports.

---

### ✅ Summary
The **Projects Module** provides end-to-end project management capabilities for small and medium enterprises using Karyalay ERP. Designed for flexibility and clarity, it integrates seamlessly with other modules and lays a strong foundation for scalable project execution, documentation, collaboration, and analytics.


**Karyalay ERP: Project Members Section – Functional Specification**

---

### 🧩 Section: Project Members
The **Members Section** handles team association and role-based access control within each project. It defines who can view, edit, and manage project elements and ensures that only authorized individuals participate in execution and collaboration.

Route: `/projects/view/:id#members`

---

## 🎯 1) Objectives
- Manage the list of users associated with a project.
- Define project-specific roles and access levels.
- Ensure only authorized users can view or modify project data.
- Facilitate collaboration by clearly identifying ownership and responsibilities.

---

## ⚙️ 2) Key Features
- Add or remove members from a project.
- Assign project-specific roles: **Owner, Contributor, Viewer**.
- View all current members with role and join date.
- Search and filter members by name, role, or department.
- Automatically assign the project creator as **Owner**.
- Display avatars, department, and last active timestamp (optional).

---

## 🧱 3) UI Components
### A) Members List View
- Table columns: Name, Role, Department, Joined On, Last Active, Actions.
- Quick actions: Promote/Demote Role, Remove Member.
- Sort and filter by role.

### B) Add Member Modal
Fields:
- User (autocomplete from existing employees/users)
- Role (dropdown)

**Rules:**
- A user cannot be added twice.
- Only project owners or admins can add new members.
- Added users auto-notified and gain project visibility immediately.

---

## 🧮 4) Database Reference
Referencing table `project_members` from Projects DB Document.

| Field | Description |
|--------|--------------|
| `project_id` | Linked project ID |
| `user_id` | User being added |
| `role` | Role in project context (Owner/Contributor/Viewer) |
| `joined_at` | Timestamp of joining |
| `removed_at` | Null unless removed |

---

## 🔗 5) Integrations
| Module | Purpose |
|---------|----------|
| **Users** | Provides user list for assignment |
| **Roles & Permissions** | Restricts project CRUD actions per role |
| **Tasks** | Only project members can be task assignees |
| **Notifications** | Sends alerts when members are added/removed |
| **Activity Log** | Records every membership change |

---

## 🚀 6) Backend Endpoints
| Method | Route | Purpose | Auth |
|---|---|---|---|
| GET | `/api/projects/:id/members` | Fetch all project members | Yes |
| POST | `/api/projects/member/add` | Add new member to project | Owner/Admin |
| POST | `/api/projects/member/update/:id` | Update member role | Owner/Admin |
| DELETE | `/api/projects/member/remove/:id` | Remove member from project | Owner/Admin |
| GET | `/api/projects/member/search` | Search eligible users to add | Owner/Admin |

All modifications trigger updates to `project_activity_log`.

---

## 🔐 7) Role Definitions
| Role | Permissions |
|------|--------------|
| **Owner** | Full control: manage members, tasks, docs, and project details |
| **Contributor** | Can manage assigned tasks, upload documents, and view project overview |
| **Viewer** | Read-only access to project overview, documents, and phases |

Each project must have at least one **Owner** at all times.

---

## ⚙️ 8) Validations & Rules
- A project must always retain at least one Owner.
- A user cannot be assigned to a project twice.
- Member removal restricted to Owner/Admin.
- Owner role cannot be demoted unless another Owner exists.
- When a member is removed:
  - Their assigned tasks remain but are flagged `unassigned`.
  - Their access to the project is revoked.
- Added members inherit project visibility immediately.

---

## 🔔 9) Notifications
| Event | Recipient | Message |
|---|---|---|
| Member Added | User | “You’ve been added as a [Role] in project [Title].” |
| Role Updated | User | “Your project role in [Title] changed to [Role].” |
| Member Removed | User | “You’ve been removed from project [Title].” |

---

## 📊 10) Reporting / Exports
- Export member list (CSV/PDF): Name, Role, Department, Joined On.
- Option to include last active and total assigned tasks (via join from tasks table).

---

## 🧠 11) Future Enhancements
- Add team/department-level assignment.
- Role customization (define new role types like Reviewer, Observer).
- Integrate attendance/time logs for project-wise contribution tracking.

---

### ✅ Summary
The **Project Members Section** defines who can access, edit, or collaborate within each project. It ensures clarity in ownership and access control, integrates tightly with users, tasks, and activity logs, and forms the foundation of role-aware collaboration inside the Projects Module.


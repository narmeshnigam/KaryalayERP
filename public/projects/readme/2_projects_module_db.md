**Karyalay ERP: Projects Module – Database Design Document**

---

### 🧮 Database Overview
This document defines the complete database schema for the **Projects Module** in **Karyalay ERP**, covering all related entities and their inter-relationships.

The structure supports both **client-linked** and **internal projects**, including optional phases, multi-assignee tasks, and versioned activity tracking.

---

## 🧱 1. Table: `projects`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Unique project ID |
| project_code | VARCHAR(30) UNIQUE | Short unique code (auto-generated) |
| title | VARCHAR(200) | Project name/title |
| type | ENUM('Internal','Client') | Project category |
| client_id | INT, FK NULL | Linked to `clients.id` (if client-linked) |
| owner_id | INT, FK | User ID of primary project owner |
| description | TEXT NULL | Project summary or scope |
| start_date | DATE NULL | Start date of project |
| end_date | DATE NULL | End date or deadline |
| priority | ENUM('Low','Medium','High','Critical') DEFAULT 'Medium' | Importance level |
| progress | DECIMAL(5,2) DEFAULT 0.00 | % completion based on tasks |
| status | ENUM('Draft','Active','On Hold','Completed','Archived') DEFAULT 'Draft' | Lifecycle status |
| tags | TEXT NULL | Comma-separated tags for categorization |
| created_by | INT, FK | User ID of creator |
| created_at | TIMESTAMP | Record creation timestamp |
| updated_at | TIMESTAMP NULL | Last update timestamp |

---

## 🧱 2. Table: `project_phases`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Unique phase ID |
| project_id | INT, FK | Linked project ID |
| title | VARCHAR(150) | Phase title or milestone name |
| description | TEXT NULL | Optional phase description |
| start_date | DATE NULL | Phase start date |
| end_date | DATE NULL | Phase end date |
| status | ENUM('Pending','In Progress','Completed','On Hold') DEFAULT 'Pending' | Phase status |
| progress | DECIMAL(5,2) DEFAULT 0.00 | % of completion of tasks under this phase |
| sequence_order | INT DEFAULT 0 | Order of appearance in UI |
| created_at | TIMESTAMP | Record creation timestamp |
| updated_at | TIMESTAMP NULL | Last update timestamp |

---

## 🧱 3. Table: `project_tasks`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Unique task ID |
| project_id | INT, FK | Linked project |
| phase_id | INT, FK NULL | Linked phase (if applicable) |
| title | VARCHAR(200) | Task title |
| description | TEXT NULL | Task details |
| due_date | DATE NULL | Task due date |
| priority | ENUM('Low','Medium','High','Critical') DEFAULT 'Medium' | Priority level |
| status | ENUM('Pending','In Progress','Review','Completed') DEFAULT 'Pending' | Current task status |
| progress | DECIMAL(5,2) DEFAULT 0.00 | Completion percentage |
| marked_done_by | INT, FK NULL | User ID who marked task complete |
| closing_notes | TEXT NULL | Notes or comments on task closure |
| created_by | INT, FK | Creator user ID |
| created_at | TIMESTAMP | Created timestamp |
| updated_at | TIMESTAMP NULL | Last updated timestamp |

---

## 🧱 4. Table: `project_task_assignees`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Unique mapping ID |
| task_id | INT, FK | Linked task ID |
| user_id | INT, FK | Assigned user ID |
| assigned_at | TIMESTAMP | Assignment date |

---

## 🧱 5. Table: `project_members`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Unique member record |
| project_id | INT, FK | Linked project ID |
| user_id | INT, FK | Member user ID |
| role | ENUM('Owner','Contributor','Viewer') DEFAULT 'Contributor' | Project-specific role |
| joined_at | TIMESTAMP | When user was added to project |
| removed_at | TIMESTAMP NULL | When user was removed (soft delete) |

---

## 🧱 6. Table: `project_documents`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Unique file ID |
| project_id | INT, FK | Linked project ID |
| file_name | VARCHAR(255) | Original file name |
| file_path | TEXT | Upload path (`/uploads/projects/`) or Vault reference |
| doc_type | VARCHAR(100) NULL | Document category (NDA, Contract, Design, Report) |
| uploaded_by | INT, FK | User ID of uploader |
| uploaded_at | TIMESTAMP | Upload timestamp |
| version | INT DEFAULT 1 | Version number for tracking |
| is_active | BOOLEAN DEFAULT 1 | Soft delete flag |

---

## 🧱 7. Table: `project_templates`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Template ID |
| title | VARCHAR(150) | Template name |
| description | TEXT NULL | Template notes or usage context |
| created_by | INT, FK | Creator user ID |
| created_at | TIMESTAMP | Record creation timestamp |

---

## 🧱 8. Table: `project_template_tasks`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Template task ID |
| template_id | INT, FK | Linked template |
| title | VARCHAR(200) | Task title |
| description | TEXT NULL | Task description |
| priority | ENUM('Low','Medium','High') DEFAULT 'Medium' | Default priority |
| sequence_order | INT DEFAULT 0 | Display order in UI |

---

## 🧱 9. Table: `project_activity_log`
| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Unique activity ID |
| project_id | INT, FK | Linked project ID |
| user_id | INT, FK | User performing action |
| activity_type | ENUM('Task','Phase','Document','Status','General') | Category of action |
| reference_id | INT NULL | Related record (task, doc, etc.) |
| description | TEXT | Details of activity |
| created_at | TIMESTAMP | Logged timestamp |

---

## 🔗 10. Relationships Summary
| Table | Relation | References |
|--------|-----------|-------------|
| `project_phases` | Many-to-one | `projects.id` |
| `project_tasks` | Many-to-one | `projects.id`, `project_phases.id` |
| `project_task_assignees` | Many-to-one | `project_tasks.id`, `users.id` |
| `project_members` | Many-to-one | `projects.id`, `users.id` |
| `project_documents` | Many-to-one | `projects.id` |
| `project_templates` | Independent | Optional pre-saved task lists |
| `project_template_tasks` | Many-to-one | `project_templates.id` |
| `project_activity_log` | Many-to-one | `projects.id`, `users.id` |

---

## 🧩 11. Design Considerations
- All date/time fields stored in UTC; conversions handled in UI.
- All deletions are soft (retain record for audit trail).
- Cascade updates for member removals (revoke task assignments).
- Progress auto-calculated via triggers or scheduled job.
- Indexing: `project_id`, `phase_id`, and `status` for performance.

---

### ✅ Summary
This database schema provides a solid backbone for the **Projects Module** — enabling structured project creation, task management, multi-user collaboration, and historical tracking. It is relational, scalable, and designed to align perfectly with other ERP modules like Clients, Users, Contacts, and the Document Vault.


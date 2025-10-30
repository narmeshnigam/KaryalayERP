**Karyalay ERP: Project Documents Section – Functional Specification**

---

### 🧩 Section: Project Documents
The **Documents Section** of the Projects Module handles all files and attachments related to a specific project. It ensures centralized, secure, and structured file storage with integration to the **Document Vault** for version tracking and easy retrieval.

Route: `/projects/view/:id#documents`

---

## 🎯 1) Objectives
- Provide a unified space to store, manage, and share project-related documents.
- Maintain version control and audit trail for document updates.
- Enable contextual file access for project members.
- Integrate with the organization-wide Document Vault.

---

## ⚙️ 2) Key Features
- Upload and manage multiple documents per project.
- Categorize documents by type (Contract, Design, Report, Drawing, etc.).
- Version control: upload new versions without losing previous copies.
- Search and filter by file name, type, uploader, or upload date.
- Quick preview and download.
- Delete or archive obsolete files (soft delete).
- Cross-link documents with **Notebook** or **CRM** entities.

---

## 🧱 3) UI Components
### A) Documents List
- Columns: File Name, Type, Version, Uploaded By, Uploaded On, Actions.
- Quick actions: Download, Upload New Version, Delete, View Details.
- File type icons and color tags for quick recognition.

### B) Upload Modal
Fields:
- File Upload (drag-drop or select)
- Document Type (dropdown)
- Version Comment (optional)

Rules:
- Allowed types: PDF, DOCX, XLSX, PNG, JPG, ZIP.
- Max file size: 25MB.
- Each upload logs an entry in the project activity log.

---

## 🧮 4) Database Reference
Referencing table `project_documents` from the Projects DB Document.

| Field | Description |
|--------|--------------|
| `project_id` | Linked project ID |
| `file_name` | Original file name |
| `file_path` | Storage path or vault reference |
| `doc_type` | Document category (e.g., Contract, Design) |
| `uploaded_by` | Uploader user ID |
| `uploaded_at` | Timestamp of upload |
| `version` | Incremental version number |
| `is_active` | 1 = active, 0 = archived |

Versioning handled by incrementing `version` when re-uploaded with same file name or doc_type.

---

## 🔗 5) Integrations
| Module | Purpose |
|---------|----------|
| **Document Vault** | Centralized document repository with shared reference paths |
| **Notebook** | Notes can embed links to project documents |
| **Activity Log** | Records every upload, version update, and delete action |
| **Members** | Controls visibility and access for each document |

---

## 🚀 6) Backend Endpoints
| Method | Route | Purpose | Auth |
|---|---|---|---|
| GET | `/api/projects/:id/documents` | List all project documents | Yes |
| POST | `/api/projects/document/upload` | Upload a new document | Member with upload rights |
| POST | `/api/projects/document/version/:id` | Upload new version | Owner/Admin |
| DELETE | `/api/projects/document/delete/:id` | Soft delete document | Owner/Admin |
| GET | `/api/projects/document/download/:id` | Download document file | Authorized user |

All updates generate entries in `project_activity_log`.

---

## 🔐 7) Permissions
| Role | View | Upload | Replace Version | Delete |
|---|---|---|---|---|
| Admin | ✔ | ✔ | ✔ | ✔ |
| Manager | ✔ | ✔ | ✔ | ✔ |
| Contributor | ✔ | ✔ | ✖ | ✖ |
| Viewer | ✔ | ✖ | ✖ | ✖ |

Unauthorized access → redirect to `/unauthorized`.

---

## ⚙️ 8) Functional Rules
- File naming convention: `[project_code]_[doc_type]_[timestamp].[ext]`.
- Each version retains audit data (uploaded_by, uploaded_at, version_comment).
- Versioning optional: if disabled, overwrites existing file reference.
- Soft-deleted files (`is_active=0`) hidden from lists but retrievable by admin.
- Vault synchronization runs nightly to ensure consistency.

---

## 🔔 9) Notifications
| Event | Recipient | Message |
|---|---|---|
| Document Uploaded | Project Owner | “New document [file_name] uploaded to project [title].” |
| New Version Added | Project Members | “Updated version of [file_name] is now available.” |
| Document Deleted | Project Owner | “[file_name] was archived from project [title].” |

---

## 📊 10) Exports / Reports
- Export CSV or PDF summary of all project documents.
- Columns: File Name, Type, Uploaded By, Date, Version.
- Filter: date range, doc type, uploader.

---

## 🧠 11) Future Enhancements
- Document approval workflows.
- OCR and full-text document search.
- Preview thumbnails and embedded viewers.
- Auto-versioning based on file hash.
- External link generation for client access.

---

### ✅ Summary
The **Project Documents Section** acts as a controlled, secure, and version-aware storage hub within each project. With full integration into the Document Vault, activity log, and member permissions, it ensures smooth collaboration and reliable record management across all project assets in Karyalay ERP.


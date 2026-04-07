BEGIN;

INSERT INTO tbl_permissions (code, description)
VALUES ('marks.review', 'Review and approve marks')
ON CONFLICT (code) DO NOTHING;

INSERT INTO tbl_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM tbl_roles r
JOIN tbl_permissions p ON p.code = 'marks.review'
WHERE r.name IN ('Super Admin','School Admin')
ON CONFLICT DO NOTHING;

COMMIT;

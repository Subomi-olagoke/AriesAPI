-- Approve all libraries created by admins
UPDATE open_libraries 
SET is_approved = 1, 
    approval_status = 'approved',
    approval_date = NOW(),
    updated_at = NOW()
WHERE creator_id IN (SELECT id FROM users WHERE isadmin = 1)
  AND approval_status != 'approved';

-- Also approve all libraries with no creator (legacy data)
UPDATE open_libraries 
SET is_approved = 1,
    approval_status = 'approved', 
    approval_date = NOW(),
    updated_at = NOW()
WHERE creator_id IS NULL
  AND approval_status != 'approved';
  
SELECT 'Approval complete!' as status;

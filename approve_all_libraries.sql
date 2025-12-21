-- Simply approve all libraries (since they were created by admins)
UPDATE open_libraries 
SET is_approved = true, 
    approval_status = 'approved',
    approval_date = NOW(),
    updated_at = NOW()
WHERE approval_status != 'approved';

-- Show count of updated libraries
SELECT COUNT(*) as libraries_approved FROM open_libraries WHERE approval_status = 'approved';

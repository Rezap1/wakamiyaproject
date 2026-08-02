<?php
$basePath = __DIR__;

// AssignmentService
$assignmentServiceContent = <<<PHP
<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\AssignmentRepositoryInterface;

class AssignmentService
{
    protected \$repository;
    protected \$notificationService;

    public function __construct(AssignmentRepositoryInterface \$repository, NotificationService \$notificationService)
    {
        \$this->repository = \$repository;
        \$this->notificationService = \$notificationService;
    }

    public function getAll()
    {
        return \$this->repository->fetchAll();
    }

    public function getById(\$id)
    {
        return \$this->repository->findById(\$id);
    }

    public function generateId()
    {
        return \$this->repository->generateNewId('ASN', 6);
    }

    public function validateAssignment(array \$data)
    {
        if (isset(\$data['Publish_Date']) && isset(\$data['Deadline'])) {
            if (strtotime(\$data['Publish_Date']) >= strtotime(\$data['Deadline'])) {
                throw new \Exception('Deadline must be greater than Publish Date.');
            }
        }
    }

    public function create(array \$data)
    {
        \$this->validateAssignment(\$data);
        if (!isset(\$data['Assignment_ID'])) {
            \$data['Assignment_ID'] = \$this->generateId();
        }
        \$data['Created_At'] = now()->toDateTimeString();
        
        \$result = \$this->repository->create(\$data);
        \$this->repository->clearCache();
        
        if ((\$data['Status'] ?? '') === 'Published') {
            // Target notifications to students (simplified: assume frontend handles class students logic)
            // Just simulate notification trigger
            // \$this->notificationService->notifyUser('ALL_CLASS_' . \$data['Class_ID'], ...);
        }
        return \$result;
    }
    
    public function update(\$id, array \$data)
    {
        \$this->validateAssignment(\$data);
        \$data['Updated_At'] = now()->toDateTimeString();
        \$result = \$this->repository->update(\$id, \$data);
        \$this->repository->clearCache();
        return \$result;
    }
    
    public function delete(\$id)
    {
        \$result = \$this->repository->softDelete(\$id);
        \$this->repository->clearCache();
        return \$result;
    }
}
PHP;
file_put_contents("$basePath/app/Services/Core/AssignmentService.php", $assignmentServiceContent);

// SubmissionService
$submissionServiceContent = <<<PHP
<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\SubmissionRepositoryInterface;

class SubmissionService
{
    protected \$repository;

    public function __construct(SubmissionRepositoryInterface \$repository)
    {
        \$this->repository = \$repository;
    }

    public function getAll()
    {
        return \$this->repository->fetchAll();
    }

    public function getById(\$id)
    {
        return \$this->repository->findById(\$id);
    }

    public function generateId()
    {
        return \$this->repository->generateNewId('SBM', 6);
    }

    public function validateSubmission(array \$data, \$isUpdate = false)
    {
        // One submission per student per assignment
        if (!\$isUpdate) {
            \$existing = \$this->getAll()->first(function(\$item) use (\$data) {
                return \$item['Student_ID'] === (\$data['Student_ID'] ?? '') && \$item['Assignment_ID'] === (\$data['Assignment_ID'] ?? '');
            });
            if (\$existing) {
                throw new \Exception('Student has already submitted this assignment.');
            }
        }
    }

    public function create(array \$data)
    {
        \$this->validateSubmission(\$data);
        if (!isset(\$data['Submission_ID'])) {
            \$data['Submission_ID'] = \$this->generateId();
        }
        \$data['Created_At'] = now()->toDateTimeString();
        
        \$result = \$this->repository->create(\$data);
        \$this->repository->clearCache();
        return \$result;
    }
    
    public function update(\$id, array \$data)
    {
        \$data['Updated_At'] = now()->toDateTimeString();
        \$result = \$this->repository->update(\$id, \$data);
        \$this->repository->clearCache();
        return \$result;
    }
    
    public function delete(\$id)
    {
        \$result = \$this->repository->softDelete(\$id);
        \$this->repository->clearCache();
        return \$result;
    }
}
PHP;
file_put_contents("$basePath/app/Services/Core/SubmissionService.php", $submissionServiceContent);

echo "Services updated.\n";

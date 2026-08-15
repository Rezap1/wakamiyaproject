<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class UserService
{
    protected $userRepository;
    protected $enterpriseEvent;

    public function __construct(
        UserRepositoryInterface $userRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->userRepository = $userRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllUsers()
    {
        return $this->userRepository->fetchAll();
    }

    public function getUserById($id)
    {
        return $this->userRepository->findById($id);
    }

    public function getNextUserId()
    {
        return $this->userRepository->generateNewId('USR', 6);
    }
    
    public function getUserByEmail($email)
    {
        return $this->userRepository->findByEmail($email);
    }

    public function getUserByUsername($username)
    {
        return $this->userRepository->findByUsername($username);
    }

    public function createUser(array $data)
    {
        $newId = $this->userRepository->generateNewId('USR', 6);

        $mappedData = [
            'User_ID' => $newId,
            'Username' => $data['Username'] ?? $data['Email'],
            'Password' => Hash::make($data['Password']),
            'Full_Name' => $data['Full_Name'],
            'Phone_Number' => $data['Phone_Number'] ?? '',
            'Email' => $data['Email'],
            'Employee_ID' => $data['Employee_ID'] ?? '',
            'Role_ID' => $data['Role_ID'],
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Last_Login' => '',
            'Failed_Login' => '0',
            'Last_Password_Change' => now()->toDateTimeString(),
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->userRepository->create($mappedData);

        $this->enterpriseEvent->dispatch(
            'USER',
            'CREATE',
            'USER',
            $newId,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR'],
            [],
            ['Username' => $mappedData['Username'], 'Email' => $mappedData['Email']]
        );
        
        return $mappedData;
    }
    
    public function updateUser($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        if (isset($data['Username'])) $mappedData['Username'] = $data['Username'];
        if (isset($data['Full_Name'])) $mappedData['Full_Name'] = $data['Full_Name'];
        if (isset($data['Phone_Number'])) $mappedData['Phone_Number'] = $data['Phone_Number'];
        if (isset($data['Email'])) $mappedData['Email'] = $data['Email'];
        if (isset($data['Employee_ID'])) $mappedData['Employee_ID'] = $data['Employee_ID'];
        if (isset($data['Role_ID'])) $mappedData['Role_ID'] = $data['Role_ID'];
        if (isset($data['Is_Active'])) $mappedData['Is_Active'] = $data['Is_Active'];
        if (isset($data['Notes'])) $mappedData['Notes'] = $data['Notes'];

        if (!empty($data['Password'])) {
            $mappedData['Password'] = Hash::make($data['Password']);
            $mappedData['Last_Password_Change'] = now()->toDateTimeString();
        }

        $res = $this->userRepository->update($id, $mappedData);

        $this->enterpriseEvent->dispatch(
            'USER',
            'UPDATE',
            'USER',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteUser($id)
    {
        $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
        $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
        
        $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $id);
        if ($employee) {
            throw new \Exception("Cannot delete user. User is associated with an Employee record.");
        }
        
        $student = collect($studentRepo->fetchAll())->firstWhere('User_ID', $id);
        if ($student) {
            throw new \Exception("Cannot delete user. User is associated with a Student record.");
        }
        
        $res = $this->userRepository->delete($id);

        $this->enterpriseEvent->dispatch(
            'USER',
            'DELETE',
            'USER',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR'],
            [],
            []
        );

        return $res;
    }
}

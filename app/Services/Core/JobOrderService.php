<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;

class JobOrderService
{
    protected $jobOrderRepository;
    protected $companyRepository;

    public function __construct(
        JobOrderRepositoryInterface $jobOrderRepository,
        CompanyRepositoryInterface $companyRepository
    ) {
        $this->jobOrderRepository = $jobOrderRepository;
        $this->companyRepository = $companyRepository;
    }

    public function getAllJobOrders()
    {
        $jobOrders = $this->jobOrderRepository->fetchAll();
        $companies = $this->companyRepository->fetchAll()->keyBy('Company_ID');

        // Append company data for easier access in views
        return $jobOrders->map(function ($jobOrder) use ($companies) {
            $company = $companies->get($jobOrder['Company_ID']) ?? [];
            $jobOrder['Company_Name'] = $company['Company_Name'] ?? 'Unknown';
            $jobOrder['Company_Code'] = $company['Company_Code'] ?? '-';
            return $jobOrder;
        });
    }

    public function getJobOrderById($id)
    {
        $jobOrder = $this->jobOrderRepository->findById($id);
        if ($jobOrder) {
            $company = $this->companyRepository->findById($jobOrder['Company_ID']);
            $jobOrder['Company_Name'] = $company['Company_Name'] ?? 'Unknown';
            $jobOrder['Company_Code'] = $company['Company_Code'] ?? '-';
        }
        return $jobOrder;
    }

    public function getJobOrdersByCompany($companyId)
    {
        return $this->jobOrderRepository->findByCompany($companyId);
    }

    public function createJobOrder(array $data)
    {
        $newId = $this->jobOrderRepository->generateNewId('JOB', 6);

        $mappedData = [
            'Job_Order_ID' => $newId,
            'Job_Order_Number' => $data['Job_Order_Number'] ?? '',
            'Company_ID' => $data['Company_ID'] ?? '',
            'Job_Title' => $data['Job_Title'] ?? '',
            'Job_Category' => $data['Job_Category'] ?? '',
            'Work_Location' => $data['Work_Location'] ?? '',
            'Prefecture' => $data['Prefecture'] ?? '',
            'Employment_Type' => $data['Employment_Type'] ?? '',
            'Visa_Type' => $data['Visa_Type'] ?? '',
            'Gender_Requirement' => $data['Gender_Requirement'] ?? '',
            'Minimum_Age' => $data['Minimum_Age'] ?? '',
            'Maximum_Age' => $data['Maximum_Age'] ?? '',
            'Education_Requirement' => $data['Education_Requirement'] ?? '',
            'Japanese_Level' => $data['Japanese_Level'] ?? '',
            'Required_Skill' => $data['Required_Skill'] ?? '',
            'Job_Description' => $data['Job_Description'] ?? '',
            'Basic_Salary' => $data['Basic_Salary'] ?? '',
            'Overtime_Pay' => $data['Overtime_Pay'] ?? '',
            'Working_Hours' => $data['Working_Hours'] ?? '',
            'Working_Days' => $data['Working_Days'] ?? '',
            'Holiday' => $data['Holiday'] ?? '',
            'Accommodation' => $data['Accommodation'] ?? '',
            'Meal' => $data['Meal'] ?? '',
            'Transportation' => $data['Transportation'] ?? '',
            'Insurance' => $data['Insurance'] ?? '',
            'Recruitment_Quantity' => $data['Recruitment_Quantity'] ?? '0',
            'Remaining_Quota' => $data['Recruitment_Quantity'] ?? '0', // Initially same as quantity
            'Interview_Date' => $data['Interview_Date'] ?? '',
            'Departure_Target' => $data['Departure_Target'] ?? '',
            'PIC_Employee_ID' => $data['PIC_Employee_ID'] ?? '',
            'Job_Order_Status' => $data['Job_Order_Status'] ?? 'OPEN',
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->jobOrderRepository->create($mappedData);
        
        return $mappedData;
    }
    
    public function updateJobOrder($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        $fields = [
            'Job_Order_Number', 'Company_ID', 'Job_Title', 'Job_Category', 'Work_Location', 
            'Prefecture', 'Employment_Type', 'Visa_Type', 'Gender_Requirement', 
            'Minimum_Age', 'Maximum_Age', 'Education_Requirement', 'Japanese_Level', 
            'Required_Skill', 'Job_Description', 'Basic_Salary', 'Overtime_Pay', 
            'Working_Hours', 'Working_Days', 'Holiday', 'Accommodation', 'Meal', 
            'Transportation', 'Insurance', 'Recruitment_Quantity', 'Remaining_Quota', 
            'Interview_Date', 'Departure_Target', 'PIC_Employee_ID', 'Job_Order_Status', 
            'Is_Active', 'Notes'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        return $this->jobOrderRepository->update($id, $mappedData);
    }

    public function deleteJobOrder($id)
    {
        return $this->jobOrderRepository->softDelete($id);
    }
}

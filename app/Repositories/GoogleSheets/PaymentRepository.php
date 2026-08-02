<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;

class PaymentRepository extends BaseSheetRepository implements PaymentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'FINANCE_PAYMENT';
        $this->cacheKey = 'finance_payment_sheet';
        $this->primaryKey = 'Payment_ID';
    }

    public function getAll()
    {
        return $this->fetchAll();
    }

    public function getById($id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }

}
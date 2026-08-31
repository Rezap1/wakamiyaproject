<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;

class InvoiceRepository extends BaseSheetRepository implements InvoiceRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'FINANCE_INVOICE';
        $this->cacheKey = 'finance_invoice_sheet';
        $this->primaryKey = 'Invoice_ID';
    }

    public function getAll()
    {
        return $this->fetchAll();
    }

    public function getAllFresh()
    {
        return $this->fetchAllFresh();
    }

    public function getById($id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function findByIdFresh($id)
    {
        return parent::findByIdFresh($id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }


}

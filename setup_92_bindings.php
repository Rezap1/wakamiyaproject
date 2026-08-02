<?php
$file = 'app/Providers/AppServiceProvider.php';
$content = file_get_contents($file);

$search = "public function register()
    {";
$replace = "public function register()
    {
        \$this->app->bind(\App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class, \App\Repositories\GoogleSheets\InvoiceRepository::class);
        \$this->app->bind(\App\Interfaces\GoogleSheets\PaymentRepositoryInterface::class, \App\Repositories\GoogleSheets\PaymentRepository::class);";

if (strpos($content, 'InvoiceRepositoryInterface') !== false && strpos($content, '$this->app->bind(\App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class') === false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Bindings added.";
} else {
    echo "Bindings might already exist or search string not found.";
}
?>

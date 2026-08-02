<?php
$file = 'app/Providers/AppServiceProvider.php';
$content = file_get_contents($file);

$search = "public function register()
    {";
$replace = "public function register()
    {
        \$this->app->bind(\App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class, \App\Repositories\GoogleSheets\PayrollRepository::class);
        \$this->app->bind(\App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface::class, \App\Repositories\GoogleSheets\SalaryComponentRepository::class);";

if (strpos($content, 'PayrollRepositoryInterface') === false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Bindings added.";
} else {
    echo "Bindings already exist.";
}
?>

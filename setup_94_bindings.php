<?php
$file = 'app/Providers/AppServiceProvider.php';
$content = file_get_contents($file);

$search = "public function register()
    {";
$replace = "public function register()
    {
        \$this->app->bind(\App\Interfaces\GoogleSheets\DocumentRepositoryInterface::class, \App\Repositories\GoogleSheets\DocumentRepository::class);
        \$this->app->bind(\App\Interfaces\GoogleSheets\DocumentTemplateRepositoryInterface::class, \App\Repositories\GoogleSheets\DocumentTemplateRepository::class);";

if (strpos($content, 'DocumentRepositoryInterface') === false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Bindings added.";
} else {
    echo "Bindings already exist.";
}
?>

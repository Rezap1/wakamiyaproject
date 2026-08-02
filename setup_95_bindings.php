<?php
$file = 'app/Providers/AppServiceProvider.php';
$content = file_get_contents($file);

// Let's use string replacement to add the binding if it doesn't exist
if (strpos($content, 'NotificationRepositoryInterface') === false) {
    // There was an issue with bindings in the previous phase when they were deleted
    // Let's add them at the end of register method safely
    
    $useStatement = "use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;\nuse App\Repositories\GoogleSheets\NotificationRepository;\n";
    $bindStatement = "\$this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);\n    }\n";
    
    $content = preg_replace('/class AppServiceProvider extends ServiceProvider/', $useStatement . "\nclass AppServiceProvider extends ServiceProvider", $content);
    $content = preg_replace('/}\s+\/\*\*\s+\*\s+Bootstrap any application services/', $bindStatement . "\n    /**\n     * Bootstrap any application services", $content);
    
    file_put_contents($file, $content);
    echo "Bindings added.";
} else {
    echo "Bindings already exist.";
}
?>

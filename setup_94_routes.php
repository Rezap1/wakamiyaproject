<?php
$file = 'routes/web.php';
$content = file_get_contents($file);

if (strpos($content, 'DocumentController') === false) {
    // Add use statements
    $searchUse = "use App\Http\Controllers\Hr\PayrollDocumentController;";
    $replaceUse = "use App\Http\Controllers\Hr\PayrollDocumentController;
use App\Http\Controllers\Core\DocumentController;
use App\Http\Controllers\Core\DocumentTemplateController;
use App\Http\Controllers\Core\DocumentPreviewController;";

    if (strpos($content, $searchUse) !== false) {
        $content = str_replace($searchUse, $replaceUse, $content);
    } else {
        // Just put them near the top
        $content = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/', "use Illuminate\Support\Facades\Route;\nuse App\Http\Controllers\Core\DocumentController;\nuse App\Http\Controllers\Core\DocumentTemplateController;\nuse App\Http\Controllers\Core\DocumentPreviewController;", $content, 1);
    }

    // Add routes inside the SUPERADMIN/ADMINISTRATOR group or a new DOCUMENT group
    // Actually, document routes should be accessible by ADMIN and HR.
    // Let's just put them in the generic auth middleware group
    $searchRoute = "Route::resource('payrolls', PayrollController::class);";
    $replaceRoute = "Route::resource('payrolls', PayrollController::class);
        
        // Document Management Routes
        Route::resource('documents', DocumentController::class);
        Route::resource('document-templates', DocumentTemplateController::class)->names('templates');
        Route::get('/documents/{id}/preview', [DocumentPreviewController::class, 'show'])->name('documents.preview');";
    
    $content = str_replace($searchRoute, $replaceRoute, $content);

    file_put_contents($file, $content);
    echo "Document Routes added.\n";
} else {
    echo "Document Routes already exist.\n";
}
?>

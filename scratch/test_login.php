$request = app('request');
$credentials = ['email' => 'lpkwakamiya01@gmail.com', 'password' => 'password'];
if (Auth::attempt($credentials)) {
    echo "AUTH SUCCESS\n";
    echo "User ID: " . Auth::user()->id . "\n";
    echo "Role ID: " . Auth::user()->Role_ID . "\n";
} else {
    echo "AUTH FAILED\n";
}

@include('errors.layout', [
    'code' => 503,
    'title' => 'Maintenance en cours',
    'message' => 'L\'application est en cours de mise a jour. Reessayez dans quelques minutes.',
    'image' => 'assets/img/media/500.png',
])

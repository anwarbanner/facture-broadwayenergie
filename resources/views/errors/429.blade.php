@include('errors.layout', [
    'code' => 429,
    'title' => 'Trop de tentatives',
    'message' => 'Patientez une minute avant de reessayer.',
    'image' => 'assets/img/media/404.png',
])

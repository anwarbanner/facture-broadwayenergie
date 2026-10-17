@include('errors.layout', [
    'code' => 403,
    'title' => 'Acces refuse',
    'message' => 'Cette page est reservee a un autre role. Demandez a un administrateur si vous en avez besoin.',
    'image' => 'assets/img/media/404.png',
])

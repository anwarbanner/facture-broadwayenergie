@include('errors.layout', [
    'code' => $exception->getStatusCode(),
    'title' => 'Une erreur est survenue',
    'message' => 'Revenez en arriere et reessayez ; si le probleme persiste, contactez l\'administrateur.',
    'image' => 'assets/img/media/500.png',
])

@include('errors.layout', [
    'code' => $exception->getStatusCode(),
    'title' => 'Requete impossible',
    'message' => 'La demande n\'a pas pu aboutir. Revenez en arriere et reessayez.',
    'image' => 'assets/img/media/404.png',
])

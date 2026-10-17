@include('errors.layout', [
    'code' => 404,
    'title' => 'Page introuvable',
    'message' => 'Cette page n\'existe pas ou a ete deplacee. Verifiez l\'adresse ou revenez a l\'accueil.',
    'image' => 'assets/img/media/404.png',
])

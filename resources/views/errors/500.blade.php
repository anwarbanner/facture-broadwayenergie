@include('errors.layout', [
    'code' => 500,
    'title' => 'Une erreur est survenue',
    'message' => 'Le probleme a ete enregistre. Revenez en arriere et reessayez ; s\'il persiste, contactez l\'administrateur.',
    'image' => 'assets/img/media/500.png',
])

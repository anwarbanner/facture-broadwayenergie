<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Ressource introuvable.',
                ], 404);
            }

            if ($this->isWriteRequest($request)) {
                return redirect()->back()->with('error', 'Element introuvable ou deja supprime.');
            }
        });

        $this->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Action non autorisee.',
                ], 403);
            }

            if ($this->isWriteRequest($request)) {
                return redirect()->back()->with('error', 'Vous n\'etes pas autorise a effectuer cette action.');
            }
        });

        $this->renderable(function (QueryException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $this->friendlyDatabaseMessage($e),
                ], 422);
            }

            if ($this->isWriteRequest($request)) {
                return redirect()->back()
                    ->withInput($request->except($this->dontFlash))
                    ->with('error', $this->friendlyDatabaseMessage($e));
            }
        });

        $this->renderable(function (Throwable $e, Request $request) {
            // Let Laravel render these natively: field errors in the session, 403/404/419 pages.
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Une erreur inattendue est survenue.',
                ], 500);
            }

            if ($this->isWriteRequest($request)) {
                return redirect()->back()
                    ->withInput($request->except($this->dontFlash))
                    ->with('error', 'Operation echouee. Verifiez les donnees saisies et reessayez.');
            }
        });
    }

    private function isWriteRequest(Request $request): bool
    {
        return in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function friendlyDatabaseMessage(QueryException $e): string
    {
        $sqlState = $e->errorInfo[0] ?? null;

        if ($sqlState === '23000') {
            return 'Operation impossible: une contrainte de base de donnees a ete violee (doublon ou reference inexistante).';
        }

        return 'Erreur de base de donnees pendant l\'operation. Merci de verifier les informations et de reessayer.';
    }
}

<?php

abstract class ExceptionHandler
{
    /**
     * @var ExceptionHandler $handler
     */
    private static $handler;

    public static function SetExceptionHandler(ExceptionHandler $handler)
    {
        self::$handler = $handler;
    }

    abstract public function HandleException($exception);

    public static function Handle($exception)
    {
        Log::Error('Uncaught exception: %s', $exception);

        if (isset(self::$handler)) {
            self::$handler->HandleException($exception);
        }
    }
}

class WebExceptionHandler extends ExceptionHandler
{
    /**
     * @var callable
     */
    private $callback;

    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    public function HandleException($exception)
    {
        error_log('Error: ' . $exception);
        ob_start();
        debug_print_backtrace();
        error_log(ob_get_clean());

        // Uncaught exceptions indicate a server-side failure.
        // Set 500 only while headers are still mutable.
        if (!headers_sent() && !connection_aborted()) {
            $currentStatus = http_response_code();
            if ($currentStatus === false || $currentStatus < 400) {
                http_response_code(500);
            }
        }

        $errorMessageId = ErrorMessages::UNKNOWN_ERROR;
        if (is_a($exception, 'DatabaseConnectionException')) {
            $errorMessageId = ErrorMessages::DATABASE_CONNECTION;
        } elseif (is_a($exception, 'DatabaseNotFoundException')) {
            $errorMessageId = ErrorMessages::DATABASE_NOT_FOUND;
        }

        // Technische Details auf der Fehlerseite nur für Admins ODER wenn app.debug
        // aktiv ist — so sehen Entwickler/Staging die echte Ursache, ohne sie
        // Endnutzern (oder einer späteren Prod-Instanz) preiszugeben.
        $detail = $this->buildDetail($exception);

        call_user_func($this->callback, $errorMessageId, '', $detail);
    }

    /**
     * Baut eine sichtbare Fehlerbeschreibung (Klasse, Meldung, Datei:Zeile) –
     * aber nur, wenn der aktuelle Nutzer Admin ist oder app.debug aktiviert wurde.
     * Defensiv: jeder Fehler beim Ermitteln führt zu leerem Detail (kein Leak).
     */
    private function buildDetail($exception): string
    {
        if (!($exception instanceof Throwable)) {
            return '';
        }

        $allowed = false;
        try {
            $user = ServiceLocator::GetServer()->GetUserSession();
            $allowed = ($user !== null && $user->IsAdmin);
        } catch (Throwable $e) {
            $allowed = false;
        }
        if (!$allowed) {
            try {
                $allowed = (bool)Configuration::Instance()->GetKey(ConfigKeys::APP_DEBUG, new BooleanConverter());
            } catch (Throwable $e) {
                $allowed = false;
            }
        }
        if (!$allowed) {
            return '';
        }

        return sprintf(
            "%s: %s\n%s:%d",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
    }
}

set_exception_handler(['ExceptionHandler', 'Handle']);

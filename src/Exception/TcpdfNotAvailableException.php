<?php

declare(strict_types=1);

namespace Exception;

/**
 * Exception levée quand la bibliothèque TCPDF n'est pas disponible sur le système de fichiers.
 */
class TcpdfNotAvailableException extends \RuntimeException
{
}

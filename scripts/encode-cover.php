<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/../app/images.php';
try {
    fwrite(STDOUT, normalizeCover($argv[1]));
} catch (DomainException $e) {
    exit(2);
}
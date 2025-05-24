<?php

namespace App\Repository; // It should be in the same namespace as ProductRepository

use RuntimeException; // Or use Exception, but RuntimeException is generally more appropriate for errors that occur at runtime

/**
 * Custom exception for database unique constraint violations.
 */
class DuplicateEntryException extends RuntimeException
{
    // No additional methods or properties are needed for this simple custom exception.
    // It inherits all functionality from RuntimeException.
}

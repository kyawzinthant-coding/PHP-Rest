<?php

namespace App\Validate;

use App\Core\Request;
use App\Exception\ValidationException;

class ReviewValidate extends BaseRequest
{
    protected array $dataToValidate;

    public function __construct(Request $request)
    {
        parent::__construct();
        $this->dataToValidate = array_merge($request->post, json_decode($request->body, true) ?: []);
    }

    // The 'validate' method from the abstract parent must be implemented,
    // but we can use a more specific method name for clarity.
    public function validate(bool $isUpdate = false): array
    {
        return $this->validateCreate();
    }

    public function validateCreate(): array
    {
        // Rating
        $rating = $this->dataToValidate['rating'] ?? null;
        if (empty($rating)) {
            $this->addError('rating', 'Rating is required.');
        } elseif (!filter_var($rating, FILTER_VALIDATE_INT) || $rating < 1 || $rating > 5) {
            $this->addError('rating', 'Rating must be an integer between 1 and 5.');
        }

        // Review Text (optional)
        $reviewText = $this->dataToValidate['review_text'] ?? null;
        if ($reviewText !== null && !is_string($reviewText)) {
            $this->addError('review_text', 'Review text must be a string.');
        }

        // This will throw a ValidationException if any errors were added.


        return [
            'rating' => (int)$rating,
            'review_text' => $reviewText,
        ];
    }
}

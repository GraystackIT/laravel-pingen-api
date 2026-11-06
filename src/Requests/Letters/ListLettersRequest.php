<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Letters;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Lists letters, page by page.
 *
 * JSON:API pagination and filtering, so the query keys are bracketed:
 * `page[number]`, `page[limit]`, `sort`, `filter[...]`. The filter array is
 * passed through as given rather than being modelled, because the set of
 * filterable fields is Pingen's to extend and a allowlist here would only make
 * this package the thing standing in the way.
 */
class ListLettersRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  int|null  $page  Page number, 1-based.
     * @param  int|null  $limit  Letters per page.
     * @param  string|null  $sort  Sort expression, e.g. "-created_at".
     * @param  array<string, scalar>  $filter  Filters, keyed by field name.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly ?int $page = null,
        private readonly ?int $limit = null,
        private readonly ?string $sort = null,
        private readonly array $filter = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/deliveries/letters';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->page !== null) {
            $query['page[number]'] = $this->page;
        }

        if ($this->limit !== null) {
            $query['page[limit]'] = $this->limit;
        }

        if ($this->sort !== null) {
            $query['sort'] = $this->sort;
        }

        foreach ($this->filter as $field => $value) {
            $query['filter['.$field.']'] = $value;
        }

        return $query;
    }
}

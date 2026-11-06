<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Letters;

use GraystackIT\Pingen\Requests\Concerns\BuildsJsonApiBody;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Creates a letter from a file already uploaded to a signed slot.
 *
 * With `autoSend` false the letter is created as a draft and waits for
 * {@see SendLetterRequest}. With it true the three print options become
 * mandatory, because the letter goes to production immediately and there is no
 * later call in which to name them.
 */
class CreateLetterRequest extends Request implements HasBody
{
    use BuildsJsonApiBody;

    protected Method $method = Method::POST;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  string  $fileUrl  The signed URL the file was uploaded to.
     * @param  string  $fileUrlSignature  The signature returned with that URL.
     * @param  string  $fileOriginalName  File name shown in the Pingen dashboard.
     * @param  string  $addressPosition  Where the address window sits: "left" or "right".
     * @param  bool  $autoSend  Whether to submit the letter immediately.
     * @param  string|null  $deliveryProduct  Postal product; required when auto-sending.
     * @param  string|null  $printMode  Simplex or duplex; required when auto-sending.
     * @param  string|null  $printSpectrum  Colour or greyscale; required when auto-sending.
     * @param  array<string, mixed>|null  $metaData  Arbitrary data stored with the letter.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly string $fileUrl,
        private readonly string $fileUrlSignature,
        private readonly string $fileOriginalName,
        private readonly string $addressPosition,
        private readonly bool $autoSend = false,
        private readonly ?string $deliveryProduct = null,
        private readonly ?string $printMode = null,
        private readonly ?string $printSpectrum = null,
        private readonly ?array $metaData = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/deliveries/letters';
    }

    protected function jsonApiType(): string
    {
        return 'letters';
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonApiAttributes(): array
    {
        return [
            'file_original_name' => $this->fileOriginalName,
            'file_url' => $this->fileUrl,
            'file_url_signature' => $this->fileUrlSignature,
            'address_position' => $this->addressPosition,
            'auto_send' => $this->autoSend,
            'delivery_product' => $this->deliveryProduct,
            'print_mode' => $this->printMode,
            'print_spectrum' => $this->printSpectrum,
            'meta_data' => $this->metaData,
        ];
    }
}

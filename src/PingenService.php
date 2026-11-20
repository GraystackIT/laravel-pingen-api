<?php

declare(strict_types=1);

namespace GraystackIT\Pingen;

use GraystackIT\Pingen\Auth\AccessTokenStore;
use GraystackIT\Pingen\Connectors\PingenConnector;
use GraystackIT\Pingen\Connectors\UploadConnector;
use GraystackIT\Pingen\Data\Letter;
use GraystackIT\Pingen\Data\LetterDraft;
use GraystackIT\Pingen\Data\LetterPrice;
use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Services\FileUploadService;
use GraystackIT\Pingen\Services\LetterEventService;
use GraystackIT\Pingen\Services\LetterService;
use GraystackIT\Pingen\Services\OrganisationService;
use GraystackIT\Pingen\Services\WebhookService;
use GraystackIT\Pingen\Support\Environment;
use GraystackIT\Pingen\Support\LetterRecorder;

/**
 * The entry point an application uses.
 *
 * Everything underneath is reachable and testable on its own; this class
 * exists so the common case is one line. It is bound to one organisation, and
 * {@see forOrganisation()} returns a second instance bound to another rather
 * than mutating this one — an application holding several Pingen accounts
 * therefore cannot accidentally send a letter from the wrong one, which is a
 * mistake nothing downstream would catch.
 */
class PingenService
{
    private ?LetterService $letters = null;

    private ?LetterEventService $events = null;

    private ?WebhookService $webhooks = null;

    private ?OrganisationService $organisations = null;

    private ?FileUploadService $uploads = null;

    /**
     * @param  PingenConnector  $connector  Connector bound to one organisation.
     * @param  LetterRecorder  $recorder  Writes letters to the log or the database.
     */
    public function __construct(
        private readonly PingenConnector $connector,
        private readonly LetterRecorder $recorder,
    ) {}

    /**
     * Build the service from the package configuration.
     *
     * @param  string|null  $organisationId  Organisation to bind to; null uses the configured one.
     *
     * @throws ConfigurationException When no organisation is configured.
     */
    public static function fromConfig(?string $organisationId = null): self
    {
        $environment = Environment::fromConfig();
        $tokens = AccessTokenStore::fromConfig();

        $organisationId ??= (string) config('pingen.organisation_id', '');

        return new self(
            new PingenConnector($tokens, $organisationId, $environment),
            new LetterRecorder,
        );
    }

    /**
     * A second service bound to a different organisation.
     *
     * @param  string  $organisationId  UUID of the other organisation.
     *
     * @throws ConfigurationException When the id is empty.
     */
    public function forOrganisation(string $organisationId): self
    {
        return self::fromConfig($organisationId);
    }

    /**
     * The organisation this service acts for.
     */
    public function organisationId(): string
    {
        return $this->connector->organisationId();
    }

    /**
     * Send a letter: upload the file, create the letter, submit it if asked.
     *
     * @param  LetterDraft  $draft  What to send.
     * @param  string|null  $idempotencyKey  A key of the caller's own for this letter. A repeat of it
     *                                       returns the letter created the first time rather than
     *                                       posting a second one, which is what makes a retried
     *                                       hand-over safe after a response that never arrived.
     *
     * @throws ConfigurationException When the draft names an option the configuration forbids.
     * @throws PingenException When any call fails.
     */
    public function send(LetterDraft $draft, ?string $idempotencyKey = null): Letter
    {
        return $this->letters()->uploadAndCreate($draft, $idempotencyKey);
    }

    /**
     * Submit a draft letter to production.
     *
     * @param  string  $letterId  UUID of the draft.
     * @param  string|null  $deliveryProduct  Postal product.
     * @param  string|null  $printMode  Simplex or duplex.
     * @param  string|null  $printSpectrum  Colour or greyscale.
     *
     * @throws PingenException When the API refuses.
     */
    public function submit(
        string $letterId,
        ?string $deliveryProduct = null,
        ?string $printMode = null,
        ?string $printSpectrum = null,
    ): Letter {
        return $this->letters()->send($letterId, $deliveryProduct, $printMode, $printSpectrum);
    }

    /**
     * Read one letter.
     *
     * @param  string  $letterId  UUID of the letter.
     *
     * @throws PingenException When the API refuses.
     */
    public function find(string $letterId): Letter
    {
        return $this->letters()->get($letterId);
    }

    /**
     * Stop a submitted letter before it is printed.
     *
     * @param  string  $letterId  UUID of the letter.
     *
     * @throws PingenException When the API refuses.
     */
    public function cancel(string $letterId): void
    {
        $this->letters()->cancel($letterId);
    }

    /**
     * Ask what a letter would cost, without creating one.
     *
     * @param  string  $country  Destination country, ISO 3166-1 alpha-2.
     * @param  string|null  $deliveryProduct  Postal product.
     * @param  string|null  $printMode  Simplex or duplex.
     * @param  string|null  $printSpectrum  Colour or greyscale.
     * @param  array<int, mixed>  $paperTypes  Paper type per sheet.
     *
     * @throws PingenException When the API refuses.
     */
    public function estimate(
        string $country,
        ?string $deliveryProduct = null,
        ?string $printMode = null,
        ?string $printSpectrum = null,
        array $paperTypes = [],
    ): LetterPrice {
        return $this->letters()->calculatePrice($country, $deliveryProduct, $printMode, $printSpectrum, $paperTypes);
    }

    /**
     * The letter service, for everything this class does not shortcut.
     */
    public function letters(): LetterService
    {
        return $this->letters ??= new LetterService($this->connector, $this->uploads(), $this->recorder);
    }

    /**
     * The file upload service.
     */
    public function uploads(): FileUploadService
    {
        return $this->uploads ??= new FileUploadService($this->connector, new UploadConnector);
    }

    /**
     * The letter event service.
     */
    public function events(): LetterEventService
    {
        return $this->events ??= new LetterEventService($this->connector);
    }

    /**
     * The webhook service.
     */
    public function webhooks(): WebhookService
    {
        return $this->webhooks ??= new WebhookService($this->connector);
    }

    /**
     * The organisation service.
     */
    public function organisations(): OrganisationService
    {
        return $this->organisations ??= new OrganisationService($this->connector);
    }

    /**
     * The underlying connector, for a request this package does not model.
     */
    public function connector(): PingenConnector
    {
        return $this->connector;
    }
}

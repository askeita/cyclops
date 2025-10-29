<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Repository\CrisisRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\OpenApi\Model\Operation;
use Symfony\Component\Validator\Constraints\Choice;


/**
 * Class Crisis
 */
#[ApiResource(
    description: 'Resource representing a financial or economic crisis with detailed characteristics.',
    operations: [
        new Get(
            uriTemplate: '/crises/{id}',
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new Get(
            uriTemplate: '/crises/search/by-name/{name}',
            openapi: new Operation(
                summary: "Search crises by name",
                description: "Returns a list of crises filtered by their name.",
                parameters: [
                    new Parameter(
                        name: 'name',
                        in: 'path',
                        description: "The name of crisis to filter by.",
                        required: true,
                        schema: ['name'=> "string"],
                        example: 'COVID-19 Global Recession'
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises',
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-category/{category}',
            requirements: ['category' => 'economic|Economic|financial|Financial'],
            openapi: new Operation(
                summary: "Search crises by category",
                description: "Returns a list of crises filtered by their category.",
                parameters: [
                    new Parameter(
                        name: 'category',
                        in: 'path',
                        description: "The category of crisis to filter by: 'Economic' or 'Financial'.",
                        required: true,
                        schema: ['category'=> "string"],
                        example: 'financial'
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')",
            validationContext: ['groups' => ['Default']],
            provider: CrisisRepository::class
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-origin/{origin}',
            openapi: new Operation(
                summary: "Search crises by origin",
                description: "Returns a list of crises filtered by their origin.",
                parameters: [
                    new Parameter(
                        name: 'origin',
                        in: 'path',
                        description: "The origin of crisis to filter by.",
                        required: true,
                        schema: ['type' => "string"],
                        example: "USA"
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-causes/{cause}',
            openapi: new Operation(
                summary: "Search crises by cause",
                description: "Returns a list of crises filtered by their cause.",
                parameters: [
                    new Parameter(
                        name: 'cause',
                        in: 'path',
                        description: "The cause of crisis to filter by.",
                        required: true,
                        schema: ['type' => "string"],
                        example: 'subprime'
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-start-date/{date}',
            openapi: new Operation(
                summary: "Search crises by start date",
                description: "Returns a list of crises filtered by their start date.",
                parameters: [
                    new Parameter(
                        name: 'date',
                        in: 'path',
                        description: 'The start date (available formats: "YYYY-MM-DD", "YYYY-MM", "YYYY-MM-DD").',
                        required: true,
                        schema: ['tyoe' => "string", 'format' => "date"],
                        example: '2008-01-01'
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-end-date/{date}',
            openapi: new Operation(
                summary: "Search crises by end date",
                description: "Returns a list of crises filtered by their end date.",
                parameters: [
                    new Parameter(
                        name: 'date',
                        in: 'path',
                        description: 'The end date (available formats: "YYYY-MM-DD", "YYYY-MM", "YYYY-MM-DD").',
                        required: true,
                        schema: ['type' => "string", 'format' => 'date'],
                        example: "2010-12-31"
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-duration/{months}',
            openapi: new Operation(
                summary: "Search crises by duration",
                description: "Returns a list of crises filtered by their duration in months.",
                parameters: [
                    new Parameter(
                        name: 'months',
                        in: 'path',
                        description: "The duration in months to filter by.",
                        required: true,
                        schema: ['type' => "integer"],
                        example: 24
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-aggravating-circumstances/{circumstance}',
            openapi: new Operation(
                summary: "Search crises by their aggravating circumstances",
                description: "Returns a list of crises filtered by their aggravating circumstances.",
                parameters: [
                    new Parameter(
                        name: 'circumstance',
                        in: 'path',
                        description: "The aggravating circumstance of crisis to filter by.",
                        required: true,
                        schema: ['type' => "string"],
                        example: "Political instability"
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-geographical-extension/{region}',
            openapi: new Operation(
                summary: "Search crises by geographical extension",
                description: "Returns a list of crises filtered by their geographical extension.",
                parameters: [
                    new Parameter(
                        name: 'region',
                        in: 'path',
                        description: 'The geographical extension to filter by.',
                        required: true,
                        schema: ['type' => "string"],
                        example: 'Global'
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-prices-evolution/{pricesEvolution}',
            openapi: new Operation(
                summary: "Search crises by prices evolution",
                description: "Returns a list of crises filtered by their prices evolution.",
                parameters: [
                    new Parameter(
                        name: 'pricesEvolution',
                        in: 'path',
                        description: 'The prices evolution to filter by.',
                        required: true,
                        schema: ['type' => "string"],
                        example: 'high inflation'
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-consequences/{consequence}',
            openapi: new Operation(
                summary: "Search crises by consequences",
                description: "Returns a list of crises filtered by their (direct AND indirect) consequences.",
                parameters: [
                    new Parameter(
                        name: 'consequence',
                        in: 'path',
                        description: "The consequences to filter by.",
                        required: true,
                        schema: ['type' => "string"],
                        example: 'recession'
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        ),
        new GetCollection(
            uriTemplate: '/crises/search/by-resolutions/{resolution}',
            openapi: new Operation(
                summary: "Search crises by resolutions",
                description: "Returns a list of crises filtered by their resolutions.",
                parameters: [
                    new Parameter(
                        name: 'resolution',
                        in: 'path',
                        description: 'The resolutions to filter by.',
                        required: true,
                        schema: ['type' => "string"],
                        example: 'bailout'
                    )
                ]
            ),
            normalizationContext: ['groups' => ['crisis:read']],
            security: "is_granted('ROLE_API_USER')"
        )
    ],
    provider: CrisisRepository::class
)]
class Crisis
{
    #[Groups(['crisis:read'])]
    #[ApiProperty(description: 'The unique identifier of the crisis.', identifier: false)]
    public ?string $id = null;

    #[Groups(['crisis:read'])]
    public ?string $name = null;

    #[Groups(['crisis:read'])]
    #[Choice(choices: ['economic', 'Economic', 'financial', 'Financial'], message: "Category must be either 'Financial' or 'Economic'.")]
    public ?string $category = null;

    #[Groups(['crisis:read'])]
    private ?string $origin = null;

    #[Groups(['crisis:read'])]
    private ?array $causes = null;

    #[Groups(['crisis:read'])]
    public ?string $startDate = null;

    #[Groups(['crisis:read'])]
    public ?string $endDate = null;

    #[Groups(['crisis:read'])]
    private ?int $durationInMonths = null;

    #[Groups(['crisis:read'])]
    public ?array $aggravatingCircumstances = null;

    #[Groups(['crisis:read'])]
    private ?string $geographicalExtension = null;

    #[Groups(['crisis:read'])]
    public ?array $pricesEvolution = null;

    #[Groups(['crisis:read'])]
    public ?array $directConsequences = [];

    #[Groups(['crisis:read'])]
    private ?array $indirectConsequences = null;

    #[Groups(['crisis:read'])]
    private ?array $resolutions = null;

    #[Groups(['crisis:read'])]
    private ?array $references = null;


    /**
     * Get the ID of the crisis
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set the ID of the crisis
     *
     * @param string|null $id   Crisis ID
     * @return $this
     */
    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the name of the crisis
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the name of the crisis
     *
     * @param string|null $name Crisis name
     * @return $this
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the category of the crisis
     *
     * @return string|null
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * Set the category of the crisis
     *
     * @param string|null $category Crisis category
     * @return $this
     */
    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Get the origin of the crisis
     *
     * @return string|null
     */
    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    /**
     * Set the origin of the crisis
     *
     * @param string|null $origin Crisis origin
     * @return $this
     */
    public function setOrigin(?string $origin): Crisis
    {
        $this->origin = $origin;
        return $this;
    }

    /**
     * Get the causes of the crisis
     *
     * @return array|null
     */
    public function getCauses(): ?array
    {
        return $this->causes;
    }

    /**
     * Set the causes of the crisis
     *
     * @param array|null $causes Crisis causes
     * @return $this
     */
    public function setCauses(?array $causes): self
    {
        $this->causes = $causes;
        return $this;
    }

    /**
     * Get the start date of the crisis
     *
     * @return string|null
     */
    public function getStartDate(): ?string
    {
        return $this->startDate;
    }

    /**
     * Set the start date of the crisis
     *
     * @param string|null $startDate Crisis start date
     * @return $this
     */
    public function setStartDate(?string $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * Get the end date of the crisis
     *
     * @return string|null
     */
    public function getEndDate(): ?string
    {
        return $this->endDate;
    }

    /**
     * Set the end date of the crisis
     *
     * @param string|null $endDate Crisis end date
     * @return $this
     */
    public function setEndDate(?string $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * Get the duration of the crisis in months
     *
     * @return int|null
     */
    public function getDurationInMonths(): ?int
    {
        return $this->durationInMonths;
    }

    /**
     * Set the duration of the crisis in months
     *
     * @param int|null $durationInMonths Crisis duration in months
     * @return $this
     */
    public function setDurationInMonths(?int $durationInMonths): Crisis
    {
        $this->durationInMonths = $durationInMonths;
        return $this;
    }

    /**
     * Get the aggravating circumstances of the crisis
     *
     * @return array|null
     */
    public function getAggravatingCircumstances(): ?array
    {
        return $this->aggravatingCircumstances;
    }

    /**
     * Set the aggravating circumstances of the crisis
     *
     * @param array|null $aggravatingCircumstances Crisis aggravating circumstances
     * @return $this
     */
    public function setAggravatingCircumstances(?array $aggravatingCircumstances): Crisis
    {
        $this->aggravatingCircumstances = $aggravatingCircumstances;
        return $this;
    }

    /**
     * Get the geographical extension of the crisis
     *
     * @return string|null
     */
    public function getGeographicalExtension(): ?string
    {
        return $this->geographicalExtension;
    }

    /**
     * Set the geographical extension of the crisis
     *
     * @param string|null $region Crisis geographical extension
     * @return $this
     */
    public function setGeographicalExtension(?string $region): Crisis
    {
        $this->geographicalExtension = $region;
        return $this;
    }

    /**
     * Get the prices evolution during the crisis
     *
     * @return array|null
     */
    public function getPricesEvolution(): ?array
    {
        return $this->pricesEvolution;
    }

    /**
     * Set the prices evolution during the crisis
     *
     * @param array|null $pricesEvolution Crisis prices evolution
     * @return $this
     */
    public function setPricesEvolution(?array $pricesEvolution): Crisis
    {
        $this->pricesEvolution = $pricesEvolution;
        return $this;
    }

    /**
     * Get the direct consequences of the crisis
     *
     * @return array|null
     */
    public function getDirectConsequences(): ?array
    {
        return $this->directConsequences;
    }

    /**
     * Set the direct consequences of the crisis
     *
     * @param array|null $directConsequences Crisis direct consequences
     * @return $this
     */
    public function setDirectConsequences(?array $directConsequences): Crisis
    {
        $this->directConsequences = $directConsequences;
        return $this;
    }

    /**
     * Get the indirect consequences of the crisis
     *
     * @return array|null
     */
    public function getIndirectConsequences(): ?array
    {
        return $this->indirectConsequences;
    }

    /**
     * Set the indirect consequences of the crisis
     *
     * @param array|null $indirectConsequences Crisis indirect consequences
     * @return $this
     */
    public function setIndirectConsequences(?array $indirectConsequences): Crisis
    {
        $this->indirectConsequences = $indirectConsequences;
        return $this;
    }

    /**
     * Get the resolutions of the crisis
     *
     * @return array|null
     */
    public function getResolutions(): ?array
    {
        return $this->resolutions;
    }

    /**
     * Set the resolutions of the crisis
     *
     * @param array|null $resolutions Crisis resolutions
     * @return $this
     */
    public function setResolutions(?array $resolutions): Crisis
    {
        $this->resolutions = $resolutions;
        return $this;
    }

    /**
     * Get the references related to the crisis
     *
     * @return array|null
     */
    public function getReferences(): ?array
    {
        return $this->references;
    }

    /**
     * Set the references related to the crisis
     *
     * @param array|null $references Crisis references
     * @return $this
     */
    public function setReferences(?array $references): Crisis
    {
        $this->references = $references;
        return $this;
    }
}

<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use Doctrine\ORM\QueryBuilder;

class PreDefinedQueries
{
    protected array $queries = [];
    protected string $translationPrefix = '';

    public function __construct(protected string $name,
                                protected string $baseUrl,
                                protected ?string $selectedId)
    {

    }

    public function addQuery(string $id, callable $queryBuilderModifier): self
    {
        $this->queries[$id] = [
            'callback' => $queryBuilderModifier,
        ];
        return $this;
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        if ($this->selectedId && isset($this->queries[$this->selectedId])) {
            call_user_func($this->queries[$this->selectedId]['callback'], $qb);
        }
        return $qb;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrls(): array
    {
        $url_parts = parse_url($this->baseUrl);
        $baseUrl = strtok($this->baseUrl, '?');
        $params = [];
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $params);
        }
        return array_combine(array_keys($this->queries), array_map(
                function ($k) use ($baseUrl, $params) {
                    return $baseUrl.'?'.\http_build_query(array_merge($params, [
                        ($this->name) => $k]));
                }, array_keys($this->queries))
        );
    }

    public function getSelectedId(): ?string
    {
        return isset($this->queries[$this->selectedId]) ? $this->selectedId : array_key_first($this->queries);
    }

    public function getTranslationPrefix(): string
    {
        return $this->translationPrefix;
    }

    public function setTranslationPrefix(string $translationPrefix): self
    {
        $this->translationPrefix = $translationPrefix;
        return $this;
    }
}
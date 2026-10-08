<?php

namespace App\Models\Concerns;

use App\Models\BusinessEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Business-entity scoping for panel users. Two rules, no role exceptions:
 *
 *  - users flagged access_all_business_entities reach every entity, including
 *    entities created later (existing panel users, super admins included,
 *    start flagged so their old access is kept);
 *  - everyone else reaches the entity they belong to
 *    (users.business_entity_id) plus the entities ticked for them on the
 *    user form (business_entity_user).
 *
 * Resources scope their queries with limitToAccessibleBusinessEntities() and
 * check single records with canAccessBusinessEntity().
 */
trait HasBusinessEntityAccess
{
    /** @var list<int>|null */
    private ?array $accessibleBusinessEntityIdsCache = null;

    /**
     * Business entities granted to this user on top of their own.
     */
    public function accessibleBusinessEntities(): BelongsToMany
    {
        return $this->belongsToMany(BusinessEntity::class)->withTimestamps();
    }

    public function hasUnrestrictedBusinessEntityAccess(): bool
    {
        return (bool) $this->access_all_business_entities;
    }

    /**
     * Own entity plus granted entities. Memoised per instance; call
     * forgetAccessibleBusinessEntityIds() or refresh() after changing either.
     *
     * @return list<int>
     */
    public function accessibleBusinessEntityIds(): array
    {
        if ($this->accessibleBusinessEntityIdsCache !== null) {
            return $this->accessibleBusinessEntityIdsCache;
        }

        $ids = $this->relationLoaded('accessibleBusinessEntities')
            ? $this->accessibleBusinessEntities->modelKeys()
            : $this->accessibleBusinessEntities()->allRelatedIds()->all();

        if ($this->business_entity_id !== null) {
            $ids[] = $this->business_entity_id;
        }

        return $this->accessibleBusinessEntityIdsCache = array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * The entities behind accessibleBusinessEntityIds(), sorted by name.
     * Unrestricted users are not expanded here; check
     * hasUnrestrictedBusinessEntityAccess() first when rendering.
     *
     * @return Collection<int, BusinessEntity>
     */
    public function effectiveBusinessEntities(): Collection
    {
        $ids = $this->accessibleBusinessEntityIds();

        if ($ids === []) {
            return new Collection;
        }

        // Served from eager-loaded relations when a table already loaded them.
        $ownLoaded = $this->business_entity_id === null || $this->relationLoaded('businessEntity');

        if ($this->relationLoaded('accessibleBusinessEntities') && $ownLoaded) {
            $entities = new Collection($this->accessibleBusinessEntities->all());

            if ($this->businessEntity !== null) {
                $entities->push($this->businessEntity);
            }

            return $entities->unique(fn (BusinessEntity $entity) => $entity->getKey())->sortBy('name')->values();
        }

        return BusinessEntity::query()->whereKey($ids)->orderBy('name')->get();
    }

    public function canAccessBusinessEntity(BusinessEntity|int|string|null $businessEntity): bool
    {
        if ($this->hasUnrestrictedBusinessEntityAccess()) {
            return true;
        }

        $id = $businessEntity instanceof BusinessEntity ? $businessEntity->getKey() : $businessEntity;

        if ($id === null || $id === '') {
            return false;
        }

        return in_array((int) $id, $this->accessibleBusinessEntityIds(), true);
    }

    /**
     * Restrict $query to rows whose $column points at an accessible business
     * entity. Unrestricted users get the query back untouched.
     */
    public function limitToAccessibleBusinessEntities(Builder $query, string $column = 'business_entity_id'): Builder
    {
        if ($this->hasUnrestrictedBusinessEntityAccess()) {
            return $query;
        }

        return $query->whereIn($column, $this->accessibleBusinessEntityIds());
    }

    public function forgetAccessibleBusinessEntityIds(): static
    {
        $this->accessibleBusinessEntityIdsCache = null;

        return $this;
    }

    public function refresh()
    {
        $this->forgetAccessibleBusinessEntityIds();

        return parent::refresh();
    }
}

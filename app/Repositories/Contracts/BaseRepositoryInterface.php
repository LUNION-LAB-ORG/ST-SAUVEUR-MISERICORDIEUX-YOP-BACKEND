<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    /**
     * Nouveau query builder sur le modèle (requêtes spécifiques : tri, portée de publication…).
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(): Builder;

    /**
     * Récupère tous les enregistrements avec relations et conditions facultatives.
     *
     * @param array $with Relations à charger.
     * @param array $conditions Conditions au format [['field', 'operator', 'value'], ...].
     * @return \Illuminate\Support\Collection
     */
    public function all(array $with = [], array $conditions = []);

    /**
     * Récupère une liste paginée des enregistrements.
     *
     * @param array $with Relations à charger.
     * @param int $page Taille de la pagination.
     * @param array $conditions Conditions au format [['field', 'operator', 'value'], ...].
     * @param int $skip Nombre d’éléments à ignorer.
     * @param string $orderBy Champ de tri.
     * @param string $direction Direction du tri (asc|desc).
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(
        array $with = [],
        int $page = 10,
        array $conditions = [],
        int $skip = 0,
        string $orderBy = 'id',
        string $direction = 'desc'
    );    

    /**
     * Trouve un enregistrement par son ID avec relations facultatives.
     *
     * @param string $id
     * @param array $with
     * @return Model
     */
    public function find(string $id, array $with = []);

    /**
     * Crée un nouvel enregistrement.
     *
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model;

    /**
     * Met à jour un enregistrement existant.
     *
     * @param int $id
     * @param array $data
     * @return Model
     */
    public function update(int $id, array $data): Model;

    /**
     * Supprime un enregistrement.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;
}

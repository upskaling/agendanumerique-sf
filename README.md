# Agenda du numérique à Poitiers et ses environs

ce projet est une copie de https://github.com/baudelotphilippe/agendanumerique

pour installer le projet

```shell
git clone https://github.com/upskaling/agendanumerique-sf
cd agendanumerique-sf

composer install
```

pour jouer les migrations

```shell
symfony console doctrine:migrations:migrate
```

pour aller chercher les événements

```shell
symfony console app:compil
```

pour lancer le serveur

```shell
symfony serve
# ou
php -S localhost:8000 -t public/
```

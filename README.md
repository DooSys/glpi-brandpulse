# GLPI BrandPulse

GLPI BrandPulse est un plugin GLPI 11 pour regrouper deux besoins qui étaient historiquement portés par des personnalisations locales : le branding de l'interface et les compteurs opérationnels dans le header.

Le projet part de l'idée du premier plugin Modifications de Stevenes Donato, disponible ici : https://github.com/stdonato/glpi-modifications. Ce plugin permettait notamment de modifier la page de login, les logos et certains éléments d'interface. Notre ancien fork local avait aussi ajouté des compteurs métier en dur dans le header. BrandPulse reprend cette intention, mais avec une base GLPI 11 propre, maintenable et paramétrable.

## Objectifs

- Branding GLPI : titre, logos, image de login et personnalisation visuelle sans modifier le core GLPI.
- Pulse header : compteurs dans la barre haute, avec icône, couleur, ordre, libellé, lien et seuils configurables.
- Compatibilité GLPI 11 : hooks officiels, autoload Composer, endpoint AJAX dédié.
- Migration douce : reprendre les compteurs historiques comme presets, puis les rendre configurables.

## Nom du plugin

Le dépôt GitHub s'appelle :

```text
glpi-brandpulse
```

Le dossier du plugin dans GLPI doit s'appeler :

```text
brandpulse
```

GLPI utilise le nom du dossier pour appeler les fonctions du plugin, par exemple `plugin_init_brandpulse()`.

## État de la v0.1.1

Cette première version pose le socle technique :

- déclaration GLPI du plugin ;
- hooks CSS et JavaScript ;
- endpoint AJAX JSON pour les compteurs ;
- rendu header proche de l'ancien affichage ;
- limitation Pulse à l'interface central GLPI, sans affichage sur le portail helpdesk/self-service ;
- option de recherche globale compacte, réduite à une loupe puis étendue au clic ;
- presets des compteurs historiques ;
- valeurs de configuration initiales pour la future partie branding.

La page de configuration est organisée en deux catégories : Brand pour l'identité visuelle et Pulse pour les compteurs du header. La v0.1 expose une édition JSON brute ; l'écran graphique complet viendra dans les versions suivantes.





## Portée Pulse

Pulse est conçu pour l'interface `central` de GLPI : techniciens, administrateurs, superviseurs, modérateurs et profils équivalents. Le endpoint AJAX vérifie l'interface courante GLPI avant de renvoyer les compteurs, afin de ne pas injecter la barre Pulse ni l'option de recherche compacte dans le portail helpdesk/self-service ou catalogue.

Dans la catégorie Pulse, l'option de recherche compacte permet de réduire la recherche globale du header à une loupe. Au clic ou au focus, le champ s'étend pour permettre la saisie.

## Internationalisation

BrandPulse suit les préconisations GLPI : les chaînes sources sont en anglais britannique et toutes les chaînes visibles du plugin passent par les fonctions gettext GLPI avec le domaine `brandpulse`, par exemple `__('Text', 'brandpulse')`.

Les catalogues sont fournis sans système tiers :

```text
locales/en_GB.po
locales/en_GB.mo
locales/fr_FR.po
locales/fr_FR.mo
```

Aucune API de traduction externe n'est utilisée. Les fichiers `.po` restent éditables avec un outil gettext classique, et les fichiers `.mo` sont inclus dans les releases pour l'exécution GLPI.

## Catalogue GLPI et icône de mise à jour

L'icône de mise à jour avec le nuage dans GLPI vient du catalogue/Marketplace GLPI. Un simple tag GitHub ne suffit pas à l'afficher dans la liste native des plugins.

BrandPulse prépare le fichier de publication attendu par GLPI :

```text
brandpulse.xml
```

Les points importants sont :

- `<key>brandpulse</key>` doit correspondre exactement au dossier technique du plugin ;
- chaque entrée `<version>` doit déclarer le numéro, la compatibilité GLPI et un `download_url` ;
- le `download_url` doit pointer vers l'archive release qui contient le dossier racine `brandpulse/`.

Pour que GLPI affiche nativement la disponibilité d'une mise à jour, le plugin doit ensuite être publié sur le catalogue GLPI, puis validé pour le Marketplace si on veut l'installation/mise à jour en un clic. Tant qu'il n'est pas référencé côté catalogue GLPI, la release GitHub reste installable manuellement mais GLPI ne pourra pas afficher automatiquement le nuage de mise à jour.

## Publication d'une version

Les versions installables sont publiées depuis des tags Git au format `vX.Y.Z`.

Avant de taguer, vérifier que la constante `PLUGIN_BRANDPULSE_VERSION` dans `setup.php` correspond au tag sans le `v`.

Exemple pour publier la version `0.1.1` :

```bash
cd /home/Doonix/DooSys_GitHub/glpi-brandpulse
git status
git add .
git commit -m "Prepare GLPI BrandPulse 0.1.1"
git push origin main
git tag -a v0.1.1 -m "GLPI BrandPulse v0.1.1"
git push origin v0.1.1
```

Le tag déclenche GitHub Actions. Le workflow construit une archive installable et la publie dans la release GitHub :

```text
glpi-brandpulse-0.1.1.zip
```

L'archive contient directement le dossier GLPI attendu :

```text
brandpulse/
```

Pour tester une release sur un environnement GLPI de test :

```bash
cd /var/www/html/glpi/plugins
rm -rf brandpulse
curl -L -o /tmp/glpi-brandpulse.zip https://github.com/DooSys/glpi-brandpulse/releases/download/v0.1.1/glpi-brandpulse-0.1.1.zip
unzip -q /tmp/glpi-brandpulse.zip -d /var/www/html/glpi/plugins
```

Si le dépôt GitHub est privé, télécharger l'archive depuis l'interface GitHub ou utiliser un accès authentifié.


## Mises à jour GLPI

GLPI appelle `plugin_brandpulse_install()` lors d'une installation, mais aussi lors d'une mise à jour du plugin depuis l'écran des plugins. BrandPulse route ce hook vers `GlpiPlugin\Brandpulse\Migrator`.

Le migrateur stocke la version de schéma appliquée dans la configuration GLPI :

```text
plugin:brandpulse / schema_version
```

Chaque future version devra ajouter une migration idempotente dans `src/Migrator.php`, puis mettre à jour `schema_version` une fois la migration terminée.

Pour une mise à jour sur l'environnement de test :

```bash
cd /var/www/html/glpi/plugins
rm -rf brandpulse
unzip -q /tmp/glpi-brandpulse.zip -d /var/www/html/glpi/plugins
```

Ensuite aller dans GLPI > Configuration > Plugins et lancer l'action de mise à jour si GLPI la propose. Le plugin appliquera ses migrations et affichera la version de schéma installée dans sa page de configuration.

## Installation en développement

Depuis le serveur GLPI :

```bash
cd /var/www/html/glpi/plugins
ln -s /home/Doonix/DooSys_GitHub/glpi-brandpulse brandpulse
cd brandpulse
composer dump-autoload
```

Puis activer le plugin depuis l'interface GLPI.


## Pack d'icônes Pulse

Les compteurs Pulse utilisent par défaut un pack SVG local situé dans :

```text
public/icons/pulse/
```

Dans la configuration JSON d'un compteur, la syntaxe recommandée est :

```json
"icon": "pulse:tasks"
```

BrandPulse résout alors automatiquement `pulse:tasks` vers `public/icons/pulse/tasks.svg`. Le rendu accepte aussi une URL SVG ou une classe CSS d'icône existante, mais le pack local est le mode par défaut afin d'éviter une dépendance tierce.

## Compteurs historiques repris comme presets

- Vos tâches à faire
- Tickets en attente
- Tickets LS-Microbio
- Mes tickets ouverts
- Tickets IT, avec exclusions historiques
- Tickets non assignés

Ces compteurs viennent de l'ancien `indicator.inc.php` local. Ils sont volontairement isolés dans un service PHP afin de pouvoir les remplacer ensuite par des règles configurables.

## Licence

GPL-3.0-or-later.

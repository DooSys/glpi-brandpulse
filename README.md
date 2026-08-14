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

## État de la v0.1.32

Cette version pose le socle technique :

- déclaration GLPI du plugin ;
- hooks CSS et JavaScript ;
- endpoint AJAX JSON pour les compteurs ;
- rendu header proche de l'ancien affichage ;
- limitation Pulse à l'interface centrale GLPI, sans affichage sur le portail helpdesk/self-service ;
- option de recherche globale compacte, réduite à une loupe puis étendue au clic ;
- presets des compteurs historiques ;
- page Brand pour le titre, favicon, logo login, logo menu gauche, fond login et message d'alerte login.
- page Pulse pour créer des compteurs, choisir icône/couleur/seuils et cibler une recherche sauvegardée GLPI.

La page de configuration est organisée en deux catégories : Brand pour l'identité visuelle et Pulse pour les compteurs du header. Pulse propose un picker d'icônes SVG local en popup avec recherche, filtre par catégorie et pagination par pages de 24 icônes. Le pack embarqué reprend la base SVG locale complète afin de laisser le choix fonctionnel le plus large possible.





## Portée Pulse

En GLPI 11, les ressources statiques du plugin restent stockées dans `public/`, mais leurs URLs publiques ne contiennent pas `/public`. BrandPulse référence donc ses assets sous `/plugins/brandpulse/css/...`, `/plugins/brandpulse/js/...` et `/plugins/brandpulse/icons/...`, conformément au routage plugin GLPI 11.

Les images importées depuis l'onglet Brand ne sont pas stockées dans le dossier du plugin. Elles sont placées dans le stockage document plugin GLPI : `GLPI_PLUGIN_DOC_DIR/brandpulse/brand`, généralement `files/_plugins/brandpulse/brand`. Le plugin les sert ensuite via `/plugins/brandpulse/front/asset.php?file=...`, ce qui permet aux URLs enregistrées de rester valides après une mise à jour du plugin.

Le branding ne doit pas dépendre d'un bloc CSS d'entité GLPI contenant des URLs d'image BrandPulse permanentes. BrandPulse charge sa propre feuille de style quand le plugin est actif, pose la classe `brandpulse-branding-enabled` seulement quand le branding est activé, puis renseigne les variables de logo GLPI. Si le plugin est désactivé, indisponible ou en attente de mise à jour, cette classe n'est plus présente et GLPI retrouve ses logos natifs.

Pulse est conçu pour l'interface `central` de GLPI : techniciens, administrateurs, superviseurs, modérateurs et profils équivalents. Le endpoint AJAX vérifie l'interface courante GLPI avant de renvoyer les compteurs, afin de ne pas injecter la barre Pulse ni l'option de recherche compacte dans le portail helpdesk/self-service ou catalogue.

Les règles complexes de compteur ne sont pas réinventées dans BrandPulse. Pour cibler des objets GLPI, des catégories, groupes, demandeurs, techniciens, statuts ou combinaisons `ET` / `OU`, il faut créer une recherche dans le moteur de recherche GLPI, la sauvegarder, puis la sélectionner dans l'onglet Pulse. BrandPulse exécute ensuite cette recherche sauvegardée avec `SavedSearch` / `Search` et récupère le `totalcount` natif.

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

Exemple pour publier la version `0.1.32` :

```bash
cd /home/Doonix/DooSys_GitHub/glpi-brandpulse
git status
git add .
git commit -m "Prepare GLPI BrandPulse 0.1.32"
git push origin main
git tag -a v0.1.32 -m "GLPI BrandPulse v0.1.32"
git push origin v0.1.32
```

Le tag déclenche GitHub Actions. Le workflow construit une archive installable et la publie dans la release GitHub :

```text
glpi-brandpulse-0.1.32.zip
```

L'archive contient directement le dossier GLPI attendu :

```text
brandpulse/
```

Pour tester une release sur un environnement GLPI de test :

```bash
cd /var/www/html/glpi/plugins
rm -rf brandpulse
curl -L -o /tmp/glpi-brandpulse.zip https://github.com/DooSys/glpi-brandpulse/releases/download/v0.1.32/glpi-brandpulse-0.1.32.zip
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
"icon": "pulse:List/Checklist Minimalistic.svg"
```

BrandPulse résout alors automatiquement cette valeur vers le SVG correspondant dans `public/icons/pulse/`. Le pack local reste le mode supporté afin d'éviter une dépendance externe au runtime et de conserver une sauvegarde Pulse déterministe.

## Compteurs historiques repris comme presets

- Vos tâches à faire
- Tickets en attente
- Mes tickets ouverts
- Tous les tickets ouverts
- Tickets non assignés

Ces compteurs reprennent seulement des cas standards. Les anciens périmètres métier locaux ne sont plus livrés en preset ; il faut les recréer avec une recherche sauvegardée GLPI et la sélectionner dans l'onglet Pulse.

## Licence

GPL-3.0-or-later.

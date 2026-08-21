# GLPI BrandPulse

![GLPI 11](https://img.shields.io/badge/GLPI-11.x-blue)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![License GPL-3.0](https://img.shields.io/badge/license-GPL--3.0--or--later-green)
![Status](https://img.shields.io/badge/status-active-success)

**GLPI BrandPulse** personnalise l'identité visuelle de GLPI et ajoute des compteurs rapides dans le header. Le plugin est pensé pour les équipes qui veulent une interface GLPI plus lisible, plus cohérente avec leur marque, et plus efficace au quotidien.

> English version below.

## Français

### Aperçu

BrandPulse regroupe quatre espaces de configuration :

- **Brand** : personnalisation des logos, du titre navigateur, du favicon, du fond de login et de l'identité visuelle.
- **Login alert** : message d'information visible sur la page de connexion, avec icône, mise en forme simple et affichage extensible.
- **Pulse** : compteurs dans le header GLPI pour suivre rapidement les tickets, les tâches ou des recherches sauvegardées.
- **Diagnostic** : informations utiles pour vérifier la version installée, l'état des options et les fichiers d'images importés.

### Captures d'écran

Ajoutez vos images dans cette section.

```text
Image à ajouter : page de configuration Brand
Image à ajouter : onglet Login alert
Image à ajouter : compteurs Pulse dans le header GLPI
Image à ajouter : page de login personnalisée
```

### Fonctionnalités Brand

- Changer le titre affiché dans l'onglet du navigateur.
- Configurer un favicon personnalisé.
- Définir des logos différents pour le menu latéral GLPI.
- Prévoir des variantes pour les thèmes clair, sombre et neutre.
- Personnaliser le logo de la page de connexion.
- Ajouter une image de fond sur la page de login.
- Conserver un rendu propre lorsque le plugin est désactivé ou en attente de mise à jour.

### Fonctionnalités Login alert

- Afficher un message sur la page de connexion.
- Choisir le type d'alerte : info, warning, danger ou success.
- Choisir une icône depuis le pack intégré.
- Rédiger un message court ou plus long.
- Ouvrir automatiquement les longs messages si besoin.
- Utiliser une mini barre de mise en forme : titre, gras, liste, code et lien.

### Fonctionnalités Pulse

- Ajouter des compteurs visibles dans le header GLPI.
- Choisir le libellé, l'icône et la couleur de chaque compteur.
- Activer ou désactiver chaque compteur.
- Réordonner les compteurs.
- Utiliser des presets simples fournis par le plugin.
- Utiliser des recherches sauvegardées GLPI pour créer des compteurs adaptés à votre organisation.
- Définir des seuils warning et critical.
- Régler l'intervalle de rafraîchissement.
- Réduire la recherche globale GLPI à une icône loupe.
- Autoriser l'affichage de Pulse profil par profil depuis l'onglet BrandPulse des profils GLPI.

### Installation

Téléchargez l'archive de release, puis déposez le dossier `brandpulse` dans le dossier `plugins` de GLPI.

```text
glpi/plugins/brandpulse
```

Activez ensuite le plugin depuis :

```text
Configuration > Plugins
```

### Compatibilité

- GLPI 11.x
- PHP 8.2 ou supérieur

### Notes d'utilisation

Pour créer des compteurs métier avancés, utilisez le moteur de recherche GLPI, sauvegardez votre recherche, puis sélectionnez-la dans l'onglet Pulse.

Les images importées depuis BrandPulse restent conservées par GLPI afin de survivre aux mises à jour du plugin.

### Licence

GPL-3.0-or-later.

---

## English

### Overview

BrandPulse brings branding and operational visibility into one GLPI plugin:

- **Brand**: customise logos, browser title, favicon, login background and visual identity.
- **Login alert**: show a styled information message on the login page, with an icon and simple formatting.
- **Pulse**: add quick counters to the GLPI header for tickets, tasks or saved searches.
- **Diagnostic**: check the installed version, enabled features and imported image files.

### Screenshots

Add your screenshots in this section.

```text
Image to add: Brand configuration page
Image to add: Login alert tab
Image to add: Pulse counters in the GLPI header
Image to add: customised login page
```

### Brand Features

- Change the browser tab title.
- Set a custom favicon.
- Configure GLPI sidebar logos.
- Use separate logo variants for light, dark and neutral themes.
- Customise the login page logo.
- Add a background image to the login page.
- Keep GLPI clean when the plugin is disabled or waiting for an update.

### Login Alert Features

- Display a message on the login page.
- Choose the alert type: info, warning, danger or success.
- Pick an icon from the bundled icon pack.
- Write short or longer messages.
- Open long messages by default when needed.
- Use a lightweight formatting toolbar: heading, bold, list, code and link.

### Pulse Features

- Add counters to the GLPI header.
- Choose each counter label, icon and colour.
- Enable or disable counters individually.
- Reorder counters.
- Use simple built-in presets.
- Use GLPI saved searches to create counters that match your organisation.
- Define warning and critical thresholds.
- Set the refresh interval.
- Collapse the global GLPI search field into a magnifier icon.
- Allow General Pulse visibility profile by profile from the BrandPulse tab in GLPI profiles.

### Installation

Download the release archive, then place the `brandpulse` folder in the GLPI `plugins` directory.

```text
glpi/plugins/brandpulse
```

Then enable the plugin from:

```text
Setup > Plugins
```

### Compatibility

- GLPI 11.x
- PHP 8.2 or higher

### Usage Notes

For advanced operational counters, create a search in GLPI, save it, then select it in the Pulse tab.

Images imported through BrandPulse are kept in GLPI storage so they remain available across plugin updates.

### License

GPL-3.0-or-later.

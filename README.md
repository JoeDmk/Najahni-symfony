# Najahni -- Plateforme Web (Symfony)

> **Najahni** est une plateforme educative et entrepreneuriale tout-en-un, developpee avec Symfony 6.4, concue pour connecter etudiants, mentors et investisseurs au sein d'un ecosysteme collaboratif intelligent.

---

## Table des matieres

- [Apercu du projet](#apercu-du-projet)
- [Fonctionnalites principales](#fonctionnalites-principales)
- [Technologies utilisees](#technologies-utilisees)
- [Architecture du projet](#architecture-du-projet)
- [Installation](#installation)
- [Configuration](#configuration)
- [Base de donnees](#base-de-donnees)
- [APIs externes](#apis-externes)
- [Securite](#securite)
- [Tests](#tests)
- [Equipe](#equipe)

---

## Apercu du projet

**Najahni** (*"Aide-moi a reussir"*) est une application web full-stack developpee dans le cadre du projet integre PIDEV 3A a **ESPRIT** (Ecole Superieure Privee d'Ingenierie et de Technologies). La plateforme vise a creer un pont entre le monde academique et professionnel en offrant des outils de formation, de mentorat, d'investissement et de gestion de projets.

### Objectifs

- Faciliter l'apprentissage en ligne avec des cours interactifs et un systeme de gamification
- Mettre en relation mentors et entrepreneurs via un matching intelligent
- Connecter investisseurs et porteurs de projets grace a l'IA
- Creer une communaute dynamique avec des groupes, evenements et discussions
- Fournir des outils d'analyse et de suivi de projets

---

## Fonctionnalites principales

### Gestion des utilisateurs
- Inscription et connexion securisees (email / mot de passe)
- Authentification Google OAuth2
- Authentification par reconnaissance faciale (Face ID)
- Protection reCAPTCHA v3 contre les bots
- Gestion de profil avec photo de profil
- Systeme de reseau social (follow / unfollow)
- Historique des connexions et detection de connexions suspectes

### Apprentissage
- Gestion de cours avec differents types et niveaux
- Systeme de commentaires sur les cours
- Gamification : badges, XP, niveaux de progression
- Generation automatique de quiz via IA
- Suivi de progression personnalise

### Communaute
- Creation et gestion de groupes thematiques
- Fils de discussion (threads) avec moderation IA
- Publications avec reactions (likes, commentaires)
- Evenements communautaires avec billetterie QR Code
- Traduction automatique des publications
- Filtrage de contenu offensant par IA
- Integration meteo pour les evenements

### Investissement
- Publication d'opportunites d'investissement
- Offres d'investissement avec contrats numeriques
- Signature electronique de contrats
- QR Code de verification des contrats
- Paiement securise via **Stripe**
- Support multi-devises avec taux de change en temps reel
- Analyse de risque economique par IA
- Matching investisseur-projet intelligent
- Chatbot conseiller en investissement
- Tableau de bord economique avec donnees World Bank

### Mentorat
- Matching mentor-entrepreneur intelligent
- Gestion des disponibilites des mentors
- Demandes et sessions de mentorat
- Notifications par email

### Gestion de projets
- Creation et suivi de projets
- Scoring de qualite des projets par IA
- Recommandations personnalisees
- Generation de business plans
- Export PDF et CSV des rapports
- Actualites sectorielles via NewsAPI

### Notifications en temps reel
- WebSocket pour les notifications instantanees
- Notifications email
- Alertes dans l'interface utilisateur

### Panel d'administration
- Dashboard administrateur complet
- Gestion CRUD de tous les modules
- Statistiques et metriques de la plateforme
- Moderation du contenu

---

## Technologies utilisees

| Categorie | Technologies |
|-----------|-------------|
| **Backend** | PHP 8.2, Symfony 6.4, Doctrine ORM 3.x |
| **Frontend** | Twig, Bootstrap 5.3, Stimulus, Turbo, CSS3 |
| **Base de donnees** | MySQL / MariaDB 10.4 |
| **Paiement** | Stripe PHP SDK 20.0 |
| **IA / ML** | Groq API (LLaMA 3.3-70b), Gemini AI |
| **Authentification** | Google OAuth2, reCAPTCHA v3, reconnaissance faciale |
| **Documents** | DOMPDF, PHPSpreadsheet, QR Code (endroid) |
| **Temps reel** | WebSocket (Ratchet) |
| **Email** | Symfony Mailer |
| **Pagination** | KnpPaginatorBundle |
| **Asset Management** | Symfony Asset Mapper |

---

## Architecture du projet

```
najahni-symfony-web/
|-- assets/                  # Assets frontend (JS, CSS)
|   |-- controllers/         # Stimulus controllers
|   +-- styles/              # Feuilles de style
|-- config/                  # Configuration Symfony
|   |-- packages/            # Configuration des bundles
|   +-- routes/              # Definition des routes
|-- migrations/              # Migrations Doctrine
|-- public/                  # Point d'entree web
|   |-- uploads/             # Fichiers uploades
|   +-- models/              # Modeles 3D (Face ID)
|-- src/
|   |-- Controller/          # 14 controleurs + 7 admin
|   |-- Entity/              # 29 entites Doctrine
|   |-- Repository/          # Repositories Doctrine
|   |-- Security/            # Authenticators personnalises
|   |-- Service/             # 30+ services metier
|   |   +-- Investment/      # Services investissement
|   |-- Command/             # Commandes console
|   +-- WebSocket/           # Serveur WebSocket
|-- templates/               # Templates Twig
|   |-- admin/               # Vues administration
|   |-- front/               # Vues front-office
|   |-- security/            # Vues authentification
|   +-- emails/              # Templates email
|-- tests/                   # Tests PHPUnit
+-- translations/            # Fichiers de traduction
```

---

## Installation

### Prerequis

- **PHP** >= 8.2
- **Composer** >= 2.x
- **MySQL** / **MariaDB** >= 10.4
- **Node.js** >= 18 (optionnel, pour les assets)
- **Symfony CLI** (recommande)

### Etapes d'installation

```bash
# 1. Cloner le depot
git clone https://github.com/votre-repo/najahni-symfony-web.git
cd najahni-symfony-web

# 2. Installer les dependances PHP
composer install

# 3. Configurer l'environnement
cp .env .env.local
# Editer .env.local avec vos parametres (voir section Configuration)

# 4. Creer la base de donnees
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Lancer le serveur de developpement
symfony server:start
# ou
php -S localhost:8000 -t public/
```

---

## Configuration

Creer un fichier `.env.local` avec les variables suivantes :

```env
# Base de donnees
DATABASE_URL="mysql://root:@127.0.0.1:3306/najahni_db"

# Stripe (Paiement)
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_PUBLIC_KEY=pk_test_xxx

# Google OAuth2
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxx

# reCAPTCHA v3
RECAPTCHA_SITE_KEY=xxx
RECAPTCHA_SECRET_KEY=xxx

# Groq API (IA)
GROQ_API_KEY=gsk_xxx

# Open Exchange Rates
EXCHANGE_RATE_API_KEY=xxx

# NewsAPI
NEWS_API_KEY=xxx

# Mailer
MAILER_DSN=smtp://user:pass@smtp.example.com:587
```

---

## Base de donnees

### Entites principales (29 tables)

| Module | Entites |
|--------|---------|
| **Utilisateurs** | User, UserConnection, UserFollow, LoginHistory |
| **Apprentissage** | Cours, CoursComment, Comment, Badge, Progression |
| **Communaute** | Post, PostReaction, Group, GroupMember, Thread, Event, EventParticipant |
| **Investissement** | InvestmentContract, InvestmentContractMessage, InvestmentOffer, InvestmentOpportunity, InvestorProfile, ContractMilestone |
| **Projets** | Projet, DonneesBusiness |
| **Mentorat** | MentorshipRequest, MentorshipSession, MentorAvailability |
| **Systeme** | Notification |

---

## APIs externes

| API | Utilisation |
|-----|------------|
| **Groq (LLaMA 3.3-70b)** | Generation de contenu, analyse de risque, moderation |
| **Google OAuth2** | Connexion sociale |
| **Stripe** | Traitement des paiements |
| **Open Exchange Rates** | Taux de change en temps reel |
| **NewsAPI** | Actualites sectorielles |
| **Open-Meteo** | Donnees meteorologiques |
| **reCAPTCHA v3** | Protection anti-bot |

---

## Securite

- Hachage de mots de passe avec **bcrypt**
- Protection CSRF sur tous les formulaires
- Authentification multi-facteurs (email, Face ID)
- Detection de connexions suspectes
- Filtrage et moderation du contenu par IA
- Validation cote serveur de toutes les entrees
- Protection reCAPTCHA v3 contre les bots
- Gestion des roles et permissions (ROLE_USER, ROLE_ADMIN)

---

## Tests

```bash
# Lancer tous les tests
php bin/phpunit

# Tests specifiques
php bin/phpunit tests/SmokeTest.php
php bin/phpunit tests/EntityRouteTest.php
php bin/phpunit tests/FeatureTest.php
php bin/phpunit tests/MentoratTest.php
```

---

## Equipe

Projet developpe par des etudiants de **3eme annee** a **ESPRIT** (Ecole Superieure Privee d'Ingenierie et de Technologies) dans le cadre du module **PIDEV** (Projet Integre de Developpement).

---

## Licence

Ce projet est developpe dans un cadre academique a **ESPRIT**.

---

## Topics et Mots-cles

`symfony` `php` `education-platform` `e-learning` `mentorship` `investment` `community` `gamification` `ai-powered` `stripe-payments` `oauth2` `face-recognition` `websocket` `real-time` `qr-code` `pdf-generation` `doctrine-orm` `twig` `bootstrap` `esprit-university` `pidev` `tunisia` `entrepreneurship` `project-management`

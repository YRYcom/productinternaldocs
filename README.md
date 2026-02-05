# ProductInternalDocs - Module PrestaShop

> Module PrestaShop pour la gestion sécurisée de documents internes confidentiels liés aux produits (back-office uniquement).

![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/License-Proprietary-red.svg)

## 📋 À propos

**ProductInternalDocs** permet aux administrateurs PrestaShop de téléverser et gérer des documents internes confidentiels pour chaque produit. Ces documents sont stockés de manière sécurisée en dehors du répertoire web public et **ne sont jamais accessibles aux clients**.

### Cas d'usage

Gérez vos documents sensibles directement depuis la fiche produit :
- 📄 Factures fournisseurs
- 📊 Fiches techniques internes
- 💰 Notes de coûts et marges
- 📑 Documents comptables
- ✅ Certificats et conformités

## ✨ Fonctionnalités principales

- 🔒 **Stockage sécurisé** hors du répertoire web
- 🔑 **Nommage UUID** impossible à deviner
- 📝 **Titres personnalisés** pour chaque document
- 🗑️ **Soft delete** avec historique complet
- 📊 **Audit trail** de toutes les actions
- ⚡ **Interface intuitive** intégrée à PrestaShop

### Formats supportés

PDF • Word • Excel • Images • Texte (max 10 MB)

## 🔒 Sécurité

✅ Authentification back-office obligatoire
✅ Stockage privé (`/var/private_documents/`)
✅ Nommage UUID v4
✅ Validation MIME stricte
✅ Logs complets via PrestaShopLogger

**Garantie** : Les clients ne peuvent ni voir ni accéder à ces documents, même en devinant l'URL.

## 🚀 Installation en production

Télécharger la release directement sur le github et l'ajouter à son projet Prestashop.

### Test en local avec Docker (optionnel)

Si vous souhaitez tester le module en local avant de le déployer :

```bash
docker-compose up -d
```

Accès : http://localhost:8000 (admin@prestashop.local / Prestashop123)

## 📖 Documentation

Pour une documentation technique complète, consultez [DOCUMENTATION.md](DOCUMENTATION.md)

## 👥 Développé par

<table>
<tr>
<td align="center" width="50%">
<h3>🏢 YRYCOM</h3>
<p>Société spécialisée dans le développement de solutions web sur mesure</p>
<a href="https://yrycom.com">🌐 Site web</a>
</td>
<td align="center" width="50%">
<h3>👨‍💻 Alexis Ladam</h3>
<p>Développeur Full Stack</p>
<p>
<a href="https://alexisladam.fr">🌐 Portfolio</a>
</p>
</td>
</tr>
</table>

## 📄 Licence

Ce projet est la propriété de **YRYCOM**. Tous droits réservés.

<div align="center">

**⭐ Si ce projet vous aide, n'hésitez pas à lui donner une étoile !**

Made with ❤️ by [YRYCOM](https://yrycom.com)

</div>

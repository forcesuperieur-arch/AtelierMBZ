---
name: paddock-design
description: Use this skill to generate well-branded interfaces and assets for Paddock, the Motoblouz motorcycle-workshop software, either for production or throwaway prototypes/mocks/etc. Contains essential design guidelines, colors, type, fonts, assets, reusable components and eight ready-made screen templates for prototyping.
user-invocable: true
---

Read the readme.md file within this skill, and explore the other available files. The fastest way in is `templates/`: eight starting points (front atelier, front client, front public, cockpit SRC, compagnon VO, e-mails, documents A4, affichage mural), each with its own README. Copy the folder you need and point the `base` line in its `boot.js` at this skill's root.
If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.
If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions, and act as an expert designer who outputs HTML artifacts _or_ production code, depending on the need.

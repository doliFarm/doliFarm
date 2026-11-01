# doliFarm Core

**doliFarm** is a comprehensive Farm Management Information System (FMIS) built as a modular extension for Dolibarr. It is designed to digitize agricultural operations, ensuring full traceability from soil to harvest.

## 🚜 Core Modules
- **Farm Dossier**: Centralized management of agricultural production cycles.
- **Plot Management**: Detailed tracking of plots, soil data, and mapping.
- **Crop Planning**: Strategic planning of crops and harvesting schedules.
- **Machinery & Assets**: Maintenance and usage logs for farm equipment.
- **Agrodrug & Input Tracking**: Management of fertilizers and phytosanitary products with safety period monitoring.
- **Cost Estimation**: Advanced financial tools for agricultural cost analysis.

## 🛠 Technical Architecture
- **Backend**: PHP (Dolibarr Framework)
- **Database**: MariaDB/MySQL (SQL scripts included in `/sql`)
- **Integration**: Designed to work alongside **doliAgroPass** for sustainability indexing.

## 🚀 Installation
1. Move the `dolifarm` folder to your Dolibarr `htdocs/custom/` directory.
2. Enable the module in **Home -> Setup -> Modules**.
3. Configure extrafields and dictionaries via the module setup page.

## 📄 License
Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>

This module is free software, released under the **GNU Affero General Public License version 3 or later** (AGPL-3.0-or-later, text in `COPYING` and `LICENSE`). If you run a modified version for users over a network (for example as a SaaS), you must offer them its source code.

Some files are derived from the ModuleBuilder templates of Dolibarr and keep the notice of their authors under the **GNU General Public License version 3 or later** (text in `COPYING-GPL-3.0`). The module as a whole is distributed under the AGPL, as allowed by section 13 of both licenses.

"doliFarm" and "doliTrace" are names of doliFarm.com: the license covers the code, not the names.

---
Developed by **Luigi Grillo**.

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
This project is licensed under the **AGPLv3 License** to protect the SaaS business model while maintaining open-source integrity.

---
Developed by **Luigi Grillo**.

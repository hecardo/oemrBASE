#----------------------------------------------------------
# a simple script to save tables before updating them.
#----------------------------------------------------------
FILE=oemr_`date +"%Y%m%d"`.sql
DBSERVER=or1rstid8y4tbge.ct9amxzt3zup.us-west-1.rds.amazonaws.com
DATABASE=openemr
USER=openemr
PASSWORD=1XhMOz1efj8w7Vv0Cx2ZiiVkU9Ej0voi
TABLES="list_options procedure_answers procedure_batch procedure_facility procedure_order procedure_order_code procedure_providers procedure_questions procedure_report procedure_result procedure_type 

mysqldump -u ${USER} -p -h ${DBSERVER} ${DATABASE} ${TABLES} > ${FILE}

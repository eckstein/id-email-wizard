<?php
function idemailwiz_table_map() {
    $table_map = array(
        // Campaigns headers
        "id" => array(
            "tableHeader" => "ID",
            "sortable" => true,
        ),
        "createdAt" => array(
            "tableHeader" => "Created",
            "fieldFormat" => "mills_date",
            "sortable" => true,
        ),
        "updatedAt" => array(
            "tableHeader" => "Updated",
            "fieldFormat" => "mills_date",
            "sortable" => true,
        ),
        "startAt" => array(
            "tableHeader" => "Start At",
            "fieldFormat" => "mills_date",
            "sortable" => true,
        ),
        "endedAt" => array(
            "tableHeader" => "Ended At",
            "fieldFormat" => "mills_date",
            "sortable" => true,
        ),
        "name" => array(
            "tableHeader" => "Name",
            "sortable" => true,
        ),
        "templateId" => array(
            "tableHeader" => "Template ID",
            "sortable" => true,
        ),
        "messageMedium" => array(
            "tableHeader" => "Medium",
            "sortable" => true,
        ),
        "labels" => array(
            "tableHeader" => "Labels",
        ),
        "createdByUserId" => array(
            "tableHeader" => "Created By",
            "sortable" => true,
        ),
        "updatedByUserId" => array(
            "tableHeader" => "Updated By",
            "sortable" => true,
        ),
        "campaignState" => array(
            "tableHeader" => "State",
            "sortable" => true,
        ),
        "sendSize" => array(
            "tableHeader" => "Send Size",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "recurringCampaignId" => array(
            "tableHeader" => "Rec. Camp. ID",
            "sortable" => true,
        ),
        "workflowId" => array(
            "tableHeader" => "Workflow ID",
            "sortable" => true,
        ),
        "listIds" => array(
            "tableHeader" => "List IDs",
        ),
        "suppressionListIds" => array(
            "tableHeader" => "Supp. List IDs",
        ),
        "type" => array(
            "tableHeader" => "Type",
            "sortable" => true,
        ),

        // Templates headers
        "templateId" => array(
            "tableHeader" => "Template ID",
            "sortable" => true,
        ),
        "createdAt" => array(
            "tableHeader" => "Created",
            "sortable" => true,
        ),
        "updatedAt" => array(
            "tableHeader" => "Updated",
            "sortable" => true,
        ),
        "name" => array(
            "tableHeader" => "Name",
            "sortable" => true,
        ),
        "creatorUserId" => array(
            "tableHeader" => "Created By",
            "sortable" => true,
        ),
        "messageTypeId" => array(
            "tableHeader" => "Type ID",
            "sortable" => true,
        ),
        "campaignId" => array(
            "tableHeader" => "Campaign ID",
            "sortable" => true,
        ),
        "fromName" => array(
            "tableHeader" => "From Name",
            "sortable" => true,
        ),
        "subject" => array(
            "tableHeader" => "Subject",
        ),
        "preheaderText" => array(
            "tableHeader" => "PH Text",
        ),
        "fromEmail" => array(
            "tableHeader" => "From Email",
            "sortable" => true,
        ),
        "replyToEmail" => array(
            "tableHeader" => "Reply To Email",
            "sortable" => true,
        ),
        "googleAnalyticsCampaignName" => array(
            "tableHeader" => "GA Campaign",
        ),
        "utmTerm" => array(
            "tableHeader" => "UTM Term",
        ),
        "utmContent" => array(
            "tableHeader" => "UTM Content",
        ),

        // Metrics headers
        "id" => array(
            "tableHeader" => "ID",
        ),
        "averageCustomConversionValue" => array(
            "tableHeader" => "Average Custom Conversion Value",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "averageOrderValue" => array(
            "tableHeader" => "Average Order Value",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "purchasesMEmail" => array(
            "tableHeader" => "Purchases/Email",
            "sortable" => true,
        ),
        "revenue" => array(
            "tableHeader" => "Revenue",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "revenueMEmail" => array(
            "tableHeader" => "Revenue/Email",
            "sortable" => true,
        ),
        "sumOfCustomConversions" => array(
            "tableHeader" => "Sum of Custom Conversions",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "totalComplaints" => array(
            "tableHeader" => "Total Complaints",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalCustomConversions" => array(
            "tableHeader" => "Total Custom Conversions",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalEmailHoldout" => array(
            "tableHeader" => "Total Email Holdout",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalEmailOpens" => array(
            "tableHeader" => "Total Email Opens",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalEmailOpensFiltered" => array(
            "tableHeader" => "Total Email Opens Filtered",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalEmailSendSkips" => array(
            "tableHeader" => "Total Email Send Skips",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalEmailSends" => array(
            "tableHeader" => "Total Email Sends",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalEmailsBounced" => array(
            "tableHeader" => "Total Emails Bounced",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalEmailsClicked" => array(
            "tableHeader" => "Total Emails Clicked",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalEmailsDelivered" => array(
            "tableHeader" => "Total Emails Delivered",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalPurchases" => array(
            "tableHeader" => "Total Purchases",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalUnsubscribes" => array(
            "tableHeader" => "Total Unsubscribes",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueCustomConversions" => array(
            "tableHeader" => "Unique Custom Conversions",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueEmailClicks" => array(
            "tableHeader" => "Clicks",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueEmailOpens" => array(
            "tableHeader" => "Opens",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueEmailOpensFiltered" => array(
            "tableHeader" => "Unique Email Opens Filtered",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueEmailOpensOrClicks" => array(
            "tableHeader" => "Unique Email Opens or Clicks",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueEmailSends" => array(
            "tableHeader" => "Sends",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueEmailsBounced" => array(
            "tableHeader" => "Unique Emails Bounced",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueEmailsDelivered" => array(
            "tableHeader" => "Unique Emails Delivered",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniquePurchases" => array(
            "tableHeader" => "Purchases",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueUnsubscribes" => array(
            "tableHeader" => "Unsubs.",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "purchasesMSms" => array(
            "tableHeader" => "Purchases/SMS",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "revenueMSms" => array(
            "tableHeader" => "Revenue/SMS",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "totalInboundSms" => array(
            "tableHeader" => "Total Inbound SMS",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalSmsBounced" => array(
            "tableHeader" => "Total SMS Bounced",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalSmsDelivered" => array(
            "tableHeader" => "Total SMS Delivered",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalSmsHoldout" => array(
            "tableHeader" => "Total SMS Holdout",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalSmsSendSkips" => array(
            "tableHeader" => "Total SMS Send Skips",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalSmsSent" => array(
            "tableHeader" => "Total SMS Sent",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "totalSmsClicks" => array(
            "tableHeader" => "Total SMS Clicks",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueInboundSms" => array(
            "tableHeader" => "Unique Inbound SMS",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueSmsBounced" => array(
            "tableHeader" => "Unique SMS Bounced",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueSmsClicks" => array(
            "tableHeader" => "Unique SMS Clicks",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueSmsDelivered" => array(
            "tableHeader" => "Unique SMS Delivered",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "uniqueSmsSent" => array(
            "tableHeader" => "Unique SMS Sent",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "lastWizUpdate" => array(
            "tableHeader" => "Last Updated",
            "sortable" => true,
        ),
        "wizOpenRate" => array(
            "tableHeader" => "Open Rate",
            "fieldFormat" => "percentage",
            "sortable" => true,
        ),
        "wizCtr" => array(
            "tableHeader" => "CTR",
            "fieldFormat" => "percentage",
            "sortable" => true,
        ),
        "wizCto" => array(
            "tableHeader" => "CTO",
            "fieldFormat" => "percentage",
            "sortable" => true,
        ),
        "wizUnsubRate" => array(
            "tableHeader" => "Unsub. Rate",
            "fieldFormat" => "percentage",
            "sortable" => true,
        ),
        "wizCompRate" => array(
            "tableHeader" => "Comp. Rate",
            "fieldFormat" => "percentage",
            "sortable" => true,
        ),
        "wizCvr" => array(
            "tableHeader" => "CVR",
            "fieldFormat" => "percentage",
            "sortable" => true,
        ),

        // Purchases headers
        "accountNumber" => array(
            "tableHeader" => "Account Number",
            "sortable" => true,
        ),
        "orderId" => array(
            "tableHeader" => "Order ID",
            "sortable" => true,
        ),
        "id" => array(
            "tableHeader" => "ID",
            "sortable" => true,
        ),
        "campaignId" => array(
            "tableHeader" => "Campaign ID",
            "sortable" => true,
        ),
        "createdAt" => array(
            "tableHeader" => "Created",
            "sortable" => true,
        ),
        "currencyTypeId" => array(
            "tableHeader" => "Currency Type ID",
            "sortable" => true,
        ),
        "eventName" => array(
            "tableHeader" => "Event Name",
        ),
        "purchaseDate" => array(
            "tableHeader" => "Purchase Date",
            "sortable" => true,
        ),
        "shoppingCartItems" => array(
            "tableHeader" => "Shopping Cart Items",
        ),
        "shoppingCartItems_discountAmount" => array(
            "tableHeader" => "Discount Amount",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "shoppingCartItems_discountCode" => array(
            "tableHeader" => "Discount Code",
            "sortable" => true,
        ),
        "shoppingCartItems_discounts" => array(
            "tableHeader" => "Discounts",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "shoppingCartItems_divisionId" => array(
            "tableHeader" => "Division ID",
            "sortable" => true,
        ),
        "shoppingCartItems_divisionName" => array(
            "tableHeader" => "Division Name",
            "sortable" => true,
        ),
        "shoppingCartItems_isSubscription" => array(
            "tableHeader" => "Is Subscription",
            "sortable" => true,
        ),
        "shoppingCartItems_locationName" => array(
            "tableHeader" => "Location Name",
            "sortable" => true,
        ),
        "shoppingCartItems_numberOfLessonsPurchasedOpl" => array(
            "tableHeader" => "# OPL Lessons",
            "sortable" => true,
        ),
        "shoppingCartItems_orderDetailId" => array(
            "tableHeader" => "Order Detail ID",
            "sortable" => true,
        ),
        "shoppingCartItems_packageType" => array(
            "tableHeader" => "Package Type",
            "sortable" => true,
        ),
        "shoppingCartItems_parentOrderDetailId" => array(
            "tableHeader" => "Parent Order Detail ID",
            "sortable" => true,
        ),
        "shoppingCartItems_predecessorOrderDetailId" => array(
            "tableHeader" => "Predecessor Order Detail ID",
            "sortable" => true,
        ),
        "shoppingCartItems_productCategory" => array(
            "tableHeader" => "Product Category",
            "sortable" => true,
        ),
        "shoppingCartItems_productSubcategory" => array(
            "tableHeader" => "Product Subcategory",
            "sortable" => true,
        ),
        "shoppingCartItems_sessionStartDateNonOpl" => array(
            "tableHeader" => "Session Start Date Non OPL",
            "sortable" => true,
        ),
        "shoppingCartItems_studentAccountNumber" => array(
            "tableHeader" => "Student Account Number",
            "sortable" => true,
        ),
        "shoppingCartItems_studentDob" => array(
            "tableHeader" => "Student DOB",
            "sortable" => true,
        ),
        "shoppingCartItems_studentGender" => array(
            "tableHeader" => "Student Gender",
            "sortable" => true,
        ),
        "shoppingCartItems_subscriptionAutoRenewDate" => array(
            "tableHeader" => "Subscription Auto Renew Date",
            "sortable" => true,
        ),
        "shoppingCartItems_totalDaysOfInstruction" => array(
            "tableHeader" => "Total Days of Instruction",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "shoppingCartItems_utmCampaign" => array(
            "tableHeader" => "UTM Campaign",
        ),
        "shoppingCartItems_utmContents" => array(
            "tableHeader" => "UTM Contents",
        ),
        "shoppingCartItems_utmMedium" => array(
            "tableHeader" => "UTM Medium",
        ),
        "shoppingCartItems_utmSource" => array(
            "tableHeader" => "UTM Source",
        ),
        "shoppingCartItems_utmTerm" => array(
            "tableHeader" => "UTM Term",
        ),
        "shoppingCartItems_categories" => array(
            "tableHeader" => "Categories",
        ),
        "shoppingCartItems_financeUnitId" => array(
            "tableHeader" => "Finance Unit ID",
            "sortable" => true,
        ),
        "shoppingCartItems_id" => array(
            "tableHeader" => "ID",
            "sortable" => true,
        ),
        "shoppingCartItems_imageUrl" => array(
            "tableHeader" => "Image URL",
        ),
        "shoppingCartItems_name" => array(
            "tableHeader" => "Name",
        ),
        "shoppingCartItems_price" => array(
            "tableHeader" => "Price",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "shoppingCartItems_quantity" => array(
            "tableHeader" => "Quantity",
            "fieldFormat" => "number",
            "sortable" => true,
        ),
        "shoppingCartItems_subsidiaryId" => array(
            "tableHeader" => "Subsidiary ID",
        ),
        "shoppingCartItems_url" => array(
            "tableHeader" => "URL",
            "sortable" => true,
        ),
        "templateId" => array(
            "tableHeader" => "Template ID",
            "sortable" => true,
        ),
        "total" => array(
            "tableHeader" => "Total",
            "fieldFormat" => "money",
            "sortable" => true,
        ),
        "userId" => array(
            "tableHeader" => "User ID",
            "sortable" => true,
        ),

    );
    return $table_map;

}
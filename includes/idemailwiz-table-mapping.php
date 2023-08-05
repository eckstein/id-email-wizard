<?php
function idemailwiz_table_map() {
    $table_map = array(
        // Campaigns headers
        "id" => array(
            "tableHeader" => "ID",
        ),
        "createdAt" => array(
            "tableHeader" => "Created",
            "fieldFormat" => "mills_date",
        ),
        "updatedAt" => array(
            "tableHeader" => "Updated",
            "fieldFormat" => "mills_date",
        ),
        "startAt" => array(
            "tableHeader" => "Start At",
            "fieldFormat" => "mills_date",
        ),
        "endedAt" => array(
            "tableHeader" => "Ended At",
            "fieldFormat" => "mills_date",
        ),
        "name" => array(
            "tableHeader" => "Name",
        ),
        "templateId" => array(
            "tableHeader" => "Template ID",
        ),
        "messageMedium" => array(
            "tableHeader" => "Medium",
        ),
        "labels" => array(
            "tableHeader" => "Labels",
        ),
        "createdByUserId" => array(
            "tableHeader" => "Created By",
        ),
        "updatedByUserId" => array(
            "tableHeader" => "Updated By",
        ),
        "campaignState" => array(
            "tableHeader" => "State",
        ),
        "sendSize" => array(
            "tableHeader" => "Send Size",
            "fieldFormat" => "number",
        ),
        "recurringCampaignId" => array(
            "tableHeader" => "Rec. Camp. ID",
        ),
        "workflowId" => array(
            "tableHeader" => "Workflow ID",
        ),
        "listIds" => array(
            "tableHeader" => "List IDs",
        ),
        "suppressionListIds" => array(
            "tableHeader" => "Supp. List IDs",
        ),
        "type" => array(
            "tableHeader" => "Type",
        ),

        // Templates headers
        "templateId" => array(
            "tableHeader" => "Template ID",
        ),
        "createdAt" => array(
            "tableHeader" => "Created",
        ),
        "updatedAt" => array(
            "tableHeader" => "Updated",
        ),
        "name" => array(
            "tableHeader" => "Name",
        ),
        "creatorUserId" => array(
            "tableHeader" => "Created By",
        ),
        "messageTypeId" => array(
            "tableHeader" => "Type ID",
        ),
        "campaignId" => array(
            "tableHeader" => "Campaign ID",
        ),
        "fromName" => array(
            "tableHeader" => "From Name",
        ),
        "subject" => array(
            "tableHeader" => "Subject",
        ),
        "preheaderText" => array(
            "tableHeader" => "PH Text",
        ),
        "fromEmail" => array(
            "tableHeader" => "From Email",
        ),
        "replyToEmail" => array(
            "tableHeader" => "Reply To Email",
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
        ),
        "averageOrderValue" => array(
            "tableHeader" => "Average Order Value",
            "fieldFormat" => "money",
        ),
        "purchasesMEmail" => array(
            "tableHeader" => "Purchases/Email",
        ),
        "revenue" => array(
            "tableHeader" => "Revenue",
            "fieldFormat" => "money",
        ),
        "revenueMEmail" => array(
            "tableHeader" => "Revenue/Email",
        ),
        "sumOfCustomConversions" => array(
            "tableHeader" => "Sum of Custom Conversions",
            "fieldFormat" => "money",
        ),
        "totalComplaints" => array(
            "tableHeader" => "Total Complaints",
            "fieldFormat" => "number",
        ),
        "totalCustomConversions" => array(
            "tableHeader" => "Total Custom Conversions",
            "fieldFormat" => "number",
        ),
        "totalEmailHoldout" => array(
            "tableHeader" => "Total Email Holdout",
            "fieldFormat" => "number",
        ),
        "totalEmailOpens" => array(
            "tableHeader" => "Total Email Opens",
            "fieldFormat" => "number",
        ),
        "totalEmailOpensFiltered" => array(
            "tableHeader" => "Total Email Opens Filtered",
            "fieldFormat" => "number",
        ),
        "totalEmailSendSkips" => array(
            "tableHeader" => "Total Email Send Skips",
            "fieldFormat" => "number",
        ),
        "totalEmailSends" => array(
            "tableHeader" => "Total Email Sends",
            "fieldFormat" => "number",
        ),
        "totalEmailsBounced" => array(
            "tableHeader" => "Total Emails Bounced",
            "fieldFormat" => "number",
        ),
        "totalEmailsClicked" => array(
            "tableHeader" => "Total Emails Clicked",
            "fieldFormat" => "number",
        ),
        "totalEmailsDelivered" => array(
            "tableHeader" => "Total Emails Delivered",
            "fieldFormat" => "number",
        ),
        "totalPurchases" => array(
            "tableHeader" => "Total Purchases",
            "fieldFormat" => "number",
        ),
        "totalUnsubscribes" => array(
            "tableHeader" => "Total Unsubscribes",
            "fieldFormat" => "number",
        ),
        "uniqueCustomConversions" => array(
            "tableHeader" => "Unique Custom Conversions",
            "fieldFormat" => "number",
        ),
        "uniqueEmailClicks" => array(
            "tableHeader" => "Clicks",
            "fieldFormat" => "number",
        ),
        "uniqueEmailOpens" => array(
            "tableHeader" => "Opens",
            "fieldFormat" => "number",
        ),
        "uniqueEmailOpensFiltered" => array(
            "tableHeader" => "Unique Email Opens Filtered",
            "fieldFormat" => "number",
        ),
        "uniqueEmailOpensOrClicks" => array(
            "tableHeader" => "Unique Email Opens or Clicks",
            "fieldFormat" => "number",
        ),
        "uniqueEmailSends" => array(
            "tableHeader" => "Sends",
            "fieldFormat" => "number",
        ),
        "uniqueEmailsBounced" => array(
            "tableHeader" => "Unique Emails Bounced",
            "fieldFormat" => "number",
        ),
        "uniqueEmailsDelivered" => array(
            "tableHeader" => "Unique Emails Delivered",
            "fieldFormat" => "number",
        ),
        "uniquePurchases" => array(
            "tableHeader" => "Purchases",
            "fieldFormat" => "number",
        ),
        "uniqueUnsubscribes" => array(
            "tableHeader" => "Unsubs.",
            "fieldFormat" => "number",
        ),
        "purchasesMSms" => array(
            "tableHeader" => "Purchases/SMS",
            "fieldFormat" => "number",
        ),
        "revenueMSms" => array(
            "tableHeader" => "Revenue/SMS",
            "fieldFormat" => "money",
        ),
        "totalInboundSms" => array(
            "tableHeader" => "Total Inbound SMS",
            "fieldFormat" => "number",
        ),
        "totalSmsBounced" => array(
            "tableHeader" => "Total SMS Bounced",
            "fieldFormat" => "number",
        ),
        "totalSmsDelivered" => array(
            "tableHeader" => "Total SMS Delivered",
            "fieldFormat" => "number",
        ),
        "totalSmsHoldout" => array(
            "tableHeader" => "Total SMS Holdout",
            "fieldFormat" => "number",
        ),
        "totalSmsSendSkips" => array(
            "tableHeader" => "Total SMS Send Skips",
            "fieldFormat" => "number",
        ),
        "totalSmsSent" => array(
            "tableHeader" => "Total SMS Sent",
            "fieldFormat" => "number",
        ),
        "totalSmsClicks" => array(
            "tableHeader" => "Total SMS Clicks",
            "fieldFormat" => "number",
        ),
        "uniqueInboundSms" => array(
            "tableHeader" => "Unique Inbound SMS",
            "fieldFormat" => "number",
        ),
        "uniqueSmsBounced" => array(
            "tableHeader" => "Unique SMS Bounced",
            "fieldFormat" => "number",
        ),
        "uniqueSmsClicks" => array(
            "tableHeader" => "Unique SMS Clicks",
            "fieldFormat" => "number",
        ),
        "uniqueSmsDelivered" => array(
            "tableHeader" => "Unique SMS Delivered",
            "fieldFormat" => "number",
        ),
        "uniqueSmsSent" => array(
            "tableHeader" => "Unique SMS Sent",
            "fieldFormat" => "number",
        ),
        "lastWizUpdate" => array(
            "tableHeader" => "Last Updated",
        ),
        "wizOpenRate" => array(
            "tableHeader" => "Open Rate",
            "fieldFormat" => "percentage",
        ),
        "wizCtr" => array(
            "tableHeader" => "CTR",
            "fieldFormat" => "percentage",
        ),
        "wizCto" => array(
            "tableHeader" => "CTO",
            "fieldFormat" => "percentage",
        ),
        "wizUnsubRate" => array(
            "tableHeader" => "Unsub. Rate",
            "fieldFormat" => "percentage",
        ),
        "wizCompRate" => array(
            "tableHeader" => "Comp. Rate",
            "fieldFormat" => "percentage",
        ),
        "wizCvr" => array(
            "tableHeader" => "CVR",
            "fieldFormat" => "percentage",
        ),

        // Purchases headers
        "accountNumber" => array(
            "tableHeader" => "Account Number",
        ),
        "orderId" => array(
            "tableHeader" => "Order ID",
        ),
        "id" => array(
            "tableHeader" => "ID",
        ),
        "campaignId" => array(
            "tableHeader" => "Campaign ID",
        ),
        "createdAt" => array(
            "tableHeader" => "Created",
        ),
        "currencyTypeId" => array(
            "tableHeader" => "Currency Type ID",
        ),
        "eventName" => array(
            "tableHeader" => "Event Name",
        ),
        "purchaseDate" => array(
            "tableHeader" => "Purchase Date",
        ),
        "shoppingCartItems" => array(
            "tableHeader" => "Shopping Cart Items",
        ),
        "shoppingCartItems_discountAmount" => array(
            "tableHeader" => "Discount Amount",
            "fieldFormat" => "money",
        ),
        "shoppingCartItems_discountCode" => array(
            "tableHeader" => "Discount Code",
        ),
        "shoppingCartItems_discounts" => array(
            "tableHeader" => "Discounts",
            "fieldFormat" => "money",
        ),
        "shoppingCartItems_divisionId" => array(
            "tableHeader" => "Division ID",
        ),
        "shoppingCartItems_divisionName" => array(
            "tableHeader" => "Division Name",
        ),
        "shoppingCartItems_isSubscription" => array(
            "tableHeader" => "Is Subscription",
        ),
        "shoppingCartItems_locationName" => array(
            "tableHeader" => "Location Name",
        ),
        "shoppingCartItems_numberOfLessonsPurchasedOpl" => array(
            "tableHeader" => "# OPL Lessons",
        ),
        "shoppingCartItems_orderDetailId" => array(
            "tableHeader" => "Order Detail ID",
        ),
        "shoppingCartItems_packageType" => array(
            "tableHeader" => "Package Type",
        ),
        "shoppingCartItems_parentOrderDetailId" => array(
            "tableHeader" => "Parent Order Detail ID",
        ),
        "shoppingCartItems_predecessorOrderDetailId" => array(
            "tableHeader" => "Predecessor Order Detail ID",
        ),
        "shoppingCartItems_productCategory" => array(
            "tableHeader" => "Product Category",
        ),
        "shoppingCartItems_productSubcategory" => array(
            "tableHeader" => "Product Subcategory",
        ),
        "shoppingCartItems_sessionStartDateNonOpl" => array(
            "tableHeader" => "Session Start Date Non OPL",
        ),
        "shoppingCartItems_studentAccountNumber" => array(
            "tableHeader" => "Student Account Number",
        ),
        "shoppingCartItems_studentDob" => array(
            "tableHeader" => "Student DOB",
        ),
        "shoppingCartItems_studentGender" => array(
            "tableHeader" => "Student Gender",
        ),
        "shoppingCartItems_subscriptionAutoRenewDate" => array(
            "tableHeader" => "Subscription Auto Renew Date",
        ),
        "shoppingCartItems_totalDaysOfInstruction" => array(
            "tableHeader" => "Total Days of Instruction",
            "fieldFormat" => "number",
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
        ),
        "shoppingCartItems_id" => array(
            "tableHeader" => "ID",
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
        ),
        "shoppingCartItems_quantity" => array(
            "tableHeader" => "Quantity",
            "fieldFormat" => "number",
        ),
        "shoppingCartItems_subsidiaryId" => array(
            "tableHeader" => "Subsidiary ID",
        ),
        "shoppingCartItems_url" => array(
            "tableHeader" => "URL",
        ),
        "templateId" => array(
            "tableHeader" => "Template ID",
        ),
        "total" => array(
            "tableHeader" => "Total",
            "fieldFormat" => "money",
        ),
        "userId" => array(
            "tableHeader" => "User ID",
        ),

    );
    return $table_map;

}
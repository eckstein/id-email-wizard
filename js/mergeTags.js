
tinymce.PluginManager.add('merge_tags_button', function(editor, url) {
  // Define the custom values for each menu item here
  const menuItems = [
     {
      text: 'Student',
      items: [
        { text: 'Student First', value: '{{{snippet "FirstName" "your child"}}}' },
        { text: 'Student Last', value: '{{StudentLast}}' },
        { text: 'Gender', value: '{{defaultIfEmpty GenderCode "U"}}' },
        { text: 'Unscheduled Lessons', value: '{{StudentArray.UnscheduledLessons}}' },
        { text: 'L10 Level', value: '{{StudentArray.L10Level}}' },
        { text: 'Account #', value: '{{StudentArray.StudentAccountNumber}}' },
        { text: 'DOB', value: 'dob' },
        { text: 'Birth Day', value: '{{StudentArray.StudentBirthDay}}' },
        { text: 'Birth Month', value: '{{StudentArray.StudentBirthMonth}}' },
        { text: 'Birth Year', value: '{{StudentArray.StudentBirthYear}}' }
      ]
    },
	{
      text: 'Pronouns',
      items: [
        { text: 'He/She/They', value: '{{{snippet "pronoun" GenderCode "S"}}}' },
        { text: 'Him/Her/Them', value: '{{{snippet "pronoun" GenderCode "O"}}}' },
        { text: 'His/Her/Their', value: '{{{snippet "pronoun" GenderCode "SP"}}}' },
        { text: 'His/Hers/Theirs', value: '{{{snippet "pronoun" GenderCode "OP"}}}' },
        { text: 'Conditional Phrase', value: '{{#if (eq genderCode "M")}}he loves{{else if (eq genderCode "F")}}she loves{{else}}they love{{/if}}' },
		
      ]
    },
	{
      text: 'Parent',
      items: [
        { text: 'Email', value: '{{email}}' },
        { text: 'Parent First', value: '{{defaultIfEmpty FirstName "Friend"}}' },
        { text: 'Parent Last', value: '{{LastName}}' }
      ]
    },
	{
	text: 'Profile',
    items: [
      { text: 'Camp Location', value: '{{CampLocation}}' },
      { text: 'Camp URL', value: '{{CampUrl}}' },
      { text: 'Last Camp Year', value: '{{LastCampYear}}' },
      { text: 'Last IPC Course', value: '{{LastIPCCourse}}' },
      { text: 'Next Course Rec', value: '{{NextCourseRec}}' },
      { text: 'Next Course Rec', value: '{{NextVtcRec}}' },
      { text: 'OPL Lessons Qty', value: '{{oplLessonsQty}}' }
      ]
    },
	
	{
      text: 'shoppingCartItems',
      items: [
		  { text: 'Categories', value: '{{shoppingCartItems.categories}}' },
		  { text: 'Discounts', value: '{{shoppingCartItems.Discounts}}' },
		  { text: 'Division Id', value: '{{shoppingCartItems.DivisionId}}' },
		  { text: 'Division Name', value: '{{shoppingCartItems.DivisionName}}' },
		  { text: 'Finance Unit ID', value: '{{shoppingCartItems.financeUnitID}}' },
		  { text: 'Id', value: '{{shoppingCartItems.id}}' },
		  { text: 'Image Url', value: '{{shoppingCartItems.imageUrl}}' },
		  { text: 'Is Subscription', value: '{{shoppingCartItems.IsSubscription}}' },
		  { text: 'Location Name', value: '{{shoppingCartItems.LocationName}}' },
		  { text: 'Name', value: '{{shoppingCartItems.name}}' },
		  { text: 'Number Of Lessons Purchased Opl', value: '{{shoppingCartItems.NumberOfLessonsPurchasedOpl}}' },
		  { text: 'Order Detail Id', value: '{{shoppingCartItems.OrderDetailId}}' },
		  { text: 'Package Type', value: '{{shoppingCartItems.PackageType}}' },
		  { text: 'Parent Order Detail Id', value: '{{shoppingCartItems.ParentOrderDetailId}}' },
		  { text: 'Predecessor Order Detail Id', value: '{{shoppingCartItems.PredecessorOrderDetailId}}' },
		  { text: 'Price', value: '{{shoppingCartItems.price}}' },
		  { text: 'Product Category', value: '{{shoppingCartItems.ProductCategory}}' },
		  { text: 'Product Subcategory', value: '{{shoppingCartItems.ProductSubcategory}}' },
		  { text: 'Quantity', value: '{{shoppingCartItems.quantity}}' },
		  { text: 'Session Start Date Non Opl', value: '{{shoppingCartItems.SessionStartDateNonOpl}}' },
		  { text: 'Student Account Number', value: '{{shoppingCartItems.StudentAccountNumber}}' },
		  { text: 'Subscription Auto Renewal Date', value: '{{shoppingCartItems.SubscriptionAutoRenewalDate}}' },
		  { text: 'Subsidiary ID', value: '{{shoppingCartItems.subsidiaryID}}' },
		  { text: 'Total Days Of Instruction', value: '{{shoppingCartItems.TotalDaysOfInstruction}}' },
		  { text: 'Url', value: '{{shoppingCartItems.url}}' },
		  { text: 'UTM Campaign', value: '{{shoppingCartItems.UTMCampaign}}' },
		  { text: 'UTM Contents', value: '{{shoppingCartItems.UTMContents}}' },
		  { text: 'UTM Medium', value: '{{shoppingCartItems.UTMMeium}}' },
		  { text: 'UTM Source', value: '{{shoppingCartItems.UTMSource}}' },
		  { text: 'UTM Term', value: '{{shoppingCartItems.UTMTerm}}' }
		]
    },
	{
      text: 'Helpers',
      items: [
		{ text: 'defaultIfEmpty', value: '{{defaultIfEmpty mergeParameter "default"}}' },
		{ text: 'HTML', value: '{{{mergeParameterHoldingHTML}}}' },
		{ text: '#each', value: '{{#each mergeParameter}}Loop content{{/each}}' },
		{ text: 'dateFormat MM-dd-yyyy ', value: '{{dateFormat inputDate format="MM-dd-yyyy" tz="America/Los Angeles"}}' },
      ]
    },
	{
      text: 'Compare/Logic',
      items: [
        
		{ text: '#if', value: '{{#if mergeParameter}}Conditional content{{/if}}' },
		{ text: '#if else', value: '{{#if mergeParameter}}Conditional content{{else}}Alt conditional content{{/if}}' },
		{ text: '#ifEq (num)', value: '{{#ifEq mergeParameter 123}}Is equal{{else}}Not equal{{/ifEq}}' },
		{ text: '#ifMatchesRegexStr', value: '{{#ifMatchesRegexStr haystack "needle"}}String matches value{{/ifMatchesRegexStr}}' },
		{ text: '#ifContainsStr', value: '{{#ifContainsStr haytack "needle"}}String matches value{{/ifContainsStr}}' },
		{ text: '#and', value: '{{#and mergeParameter1 mergeParameter2}}Conditional content{{/and}}' },
		{ text: '#and else', value: '{{#and mergeParameter1 mergeParameter2}}Both are true{{else}}One or neither are true{{/and}}' },
		{ text: 'and output', value: '{{and mergeParameter1 mergeParameter2 yes="Both are true" no="One or neither are true"}}' },
		{ text: '#or', value: '{{#or mergeParameter1 mergeParameter2}}Either are true{{/or}}' },
		{ text: '#or else', value: '{{#or mergeParameter1 mergeParameter2}}Either are true{{else}}Neither are true{{/or}}' },
		{ text: 'or output', value: '{{or mergeParameter1 mergeParameter2 yes="Either are true" no="Neither are true"}}' },
		{ text: '#not', value: '{{#not mergeParameter1 mergeParameter2}}Neither are true{{/not}}' },
		{ text: '#not else', value: '{{#not mergeParameter1 mergeParameter2}}Nither are true{{else}}Either are true{{/not}}' },
		{ text: 'not output', value: '{{not mergeParameter1 mergeParameter2 yes="Neither are true" no="Either are true"}}' },
		{ text: '#unless', value: '{{#unless mergeParameter1}}Conditional content{{/unless}}' },
      ]
    },
	{
	  text: 'Signatures',
	  items: [
		{ text: 'Ricky', value: '<strong>Ricky Bennett</strong><br/><img src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/Ricky%20Sig%202x.jpg" style="max-width: 150px; margin: 0; display: block;"/>Head of iD Tech Education' },
		{ text: 'Pete', value: '<strong>Pete Ingram-Cauchi</strong><br/><img src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/Pete-Signature.jpg" style="max-width: 150px; margin: 0; display: block;"/>CEO iD Tech and father of three iD Tech campers' },
		{ text: 'Sonia', value: '<strong>Sonia Balcazar</strong><br/><img src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/Sonia%20Balcazar%20Signature%20%281%29.png" style="max-width: 150px; margin: 0; display: block;"/>Director of Client Services, iD Tech'},
		{ text: 'Mark', value: '<strong>Mark Moreno</strong><br/><img src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/Mark%20Moreno%20Signature%20%281%29.png" style="max-width: 150px; margin: 0; display: block;"/>VP of On-Campus Programs'},
		{ text: 'Chelsea', value: '<strong>Chelsea Harder</strong><br/><img src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/1000000114%20%28669%C3%97269%29.png" style="max-width: 150px; margin: 0; display: block;"/>Associate VP of University Partnerships' },
	  ]
	}
  ];

  editor.addButton('merge_tags_button', {
    type: 'menubutton',
    text: 'Personalization',
    menu: menuItems.map(item => ({
      text: item.text,
      menu: generateMenuItems(item.items, item.text.toLowerCase().replace(/ /g, '_'))
    }))
  });

  function generateMenuItems(items, prefix) {
	  return items.map(function(item) {
		return {
		  text: item.text,
		  onclick: function() {
			editor.insertContent(item.value);
		  }
		};
	  });
	}



});


## this is Pbn Automation software connected to thousands of domains
** features **
-domains (crud) and check domain status job
- article crud with locking and status (unused,used,archive)
- article set containing articles for user
- campaigns post campaign have 4 tables (campaigns,campaign Articles,CampaignDomains,campaignPost)
- sidebar campaigns mainly  blogroll campaign have 4 tables (sidebar_campaigns,sidebar_campaign_domains,sidebar_campaign_links,sidebar_campaign_tasks)
- hiddenLink Campaigns mainly links that doesnot show on site contains manialy 4 tables(hidden_links_campaigns,hidden_links_campaigns_domains,hidden_links_campaigns_links,hidden_links_campaigns_tasks)
All have models file and controller file
similarly there are schedule campaigns and schedule sidebar campaigns 
*** now i want main thing is that there should be an manual button in all campaign maybe retry or button that manualy dispatch the job i have made in campaignController but i want it should from first even if there are 5 attempts remaining ***
*** i want that make post campaigns and schedule campaigns there same links and keywords there should be option to edit the keyword links and it should update from all remote website which are link first make it in for normal campaign then we will got manual retry for blogroll and hidden  ***
*this is Api data *
for api urls and endpoint see image api-format.png 
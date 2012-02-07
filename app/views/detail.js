{
                        // the details card
    id: 'detailCard',
    xtype: 'tabpanel',
    tabBar: {
        // the detail card contains two tabs: address and map
        docked: 'top',
        ui: 'light',
        layout: { pack: 'center' }
    },
    items: [{
            // also has a toolbar
            docked : 'top',
            xtype: 'toolbar',
            title: '',
            items: [{
                // containing a back button that slides back to list card
                text: 'Back',
                ui: 'back',
                listeners: {
                    tap: function () {
                        cards.setActiveItem(
                            cards.listCard,
                            {type:'slide', direction: 'right'}
                        );
                    }
                }
            }]
        },
        {
            // textual detail
            title: 'Contact',
            styleHtmlContent: true,
            cls: 'detail',
            tpl: [
                '<img class="photo" src="{photo_url}" width="100" height="100"/>',
                '<h2>{menu}</h2>',
                '<div class="info">',
                    '{address1}<br/>',
                    '<img src="{rating_img_url_small}"/>',
                '</div>',
                '<div class="phone x-button">',
                    '<a href="tel:{phone}">{phone}</a>',
                '</div>',
                '<div class="link x-button">',
                    '<a href="{mobile_url}">Read more</a>',
                '</div>'
            ]
        }/*,
        {
            // map detail
            title: 'Map',
            xtype: 'map',
            update: function (data) {
                // get centered on bound data
                this.map.setCenter(new google.maps.LatLng(data.latitude, data.longitude));
                this.marker.setPosition(
                    this.map.getCenter()
                );
                this.marker.setMap(this.map);
            },
            marker: new google.maps.Marker()
        }*/
    ],
    updateData: function(data) {
        // updating card cascades to update each tab
        Ext.each(this.items.items, function(item) {
            //item.update(data);
        });
        this.items.items[0].setTitle(data.menu);
    }
}
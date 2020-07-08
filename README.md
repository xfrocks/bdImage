# APIs

All APIs of bdImage follows the structure of bdApi (https://github.com/xfrocks/bdApi/blob/master/docs/api.markdown).  

All APIs require `oauth_token`.  

### GET `/posts/:postId/editor-config`

Get all image info in post

##### Request
Accepted params:
- `postId`: postID
- `fields_include`: add param "post_images"
 
Request

curl --location --request GET 'http://xenforo1.local.tinhte.vn:10080/api/index.php?/posts/88/editor-config&oauth_token=XXX&fields_include=post_images' \

Response

```
{
    "post_images": [
        {
            "image_url": "https://img.tinxe.vn/resize/1000x-/2019/04/05/XForF7yt/10-honda-cb650r-99d9.png",
            "data": "{\"url\":\"https:\\/\\/img.tinxe.vn\\/resize\\/1000x-\\/2019\\/04\\/05\\/XForF7yt\\/10-honda-cb650r-99d9.png\",\"type\":\"url\",\"filename\":\"10-honda-cb650r-99d9.png\"}"
        },
        {
            "image_url": "https://autopress.vn/uploads/userfiles/images/news_1551434285/Do-Den-Bac.jpg",
            "data": "{\"url\":\"https:\\/\\/autopress.vn\\/uploads\\/userfiles\\/images\\/news_1551434285\\/Do-Den-Bac.jpg\",\"type\":\"url\",\"filename\":\"Do-Den-Bac.jpg\"}"
        },
        {
            "image_url": "https://autopro8.mediacdn.vn/2019/3/4/hondacb650rautopro4-155169435410676540228.jpg",
            "data": "{\"url\":\"https:\\/\\/autopro8.mediacdn.vn\\/2019\\/3\\/4\\/hondacb650rautopro4-155169435410676540228.jpg\",\"type\":\"url\",\"filename\":\"hondacb650rautopro4-155169435410676540228.jpg\"}"
        },
        {
            "image_url": "https://img.tinxe.vn/resize/1000x-/2019/04/05/XForF7yt/2-honda-cb650r-efed.jpg",
            "data": "{\"url\":\"https:\\/\\/img.tinxe.vn\\/resize\\/1000x-\\/2019\\/04\\/05\\/XForF7yt\\/2-honda-cb650r-efed.jpg\",\"type\":\"url\",\"filename\":\"2-honda-cb650r-efed.jpg\"}"
        },
        {
            "image_url": "https://img.tinxe.vn/resize/1000x-/2019/04/05/XForF7yt/6-honda-cb650r-9717.jpg",
            "data": "{\"url\":\"https:\\/\\/img.tinxe.vn\\/resize\\/1000x-\\/2019\\/04\\/05\\/XForF7yt\\/6-honda-cb650r-9717.jpg\",\"type\":\"url\",\"filename\":\"6-honda-cb650r-9717.jpg\"}"
        }
    ]
}
```

### POST `/threads/:threadId/image`

Set thread image 

##### Request
Accepted params:
- `image`: json data, image data 
- `other`: url link
- `is_cover`: render as thread top cover image, default value: `false` 
- `cover_color`: cover color, default value: `''`

Example request

- Request cover image have already existed in first post 

curl --location --request POST 'http://xenforo1.local.tinhte.vn:10080/api/index.php?/threads/65/image&oauth_token=2f537b197d8c9e3db06df795bc323f07c51d39cb' \
--form 'image={"url":"https:\/\/autopro8.mediacdn.vn\/2019\/3\/4\/hondacb650rautopro4-155169435410676540228.jpg","type":"url","filename":"hondacb650rautopro4-155169435410676540228.jpg"}' \
--form 'other=' \
--form 'is_cover=1' \
--form 'cover_color=rgb(144,151,118)'


- Request cover image other 

curl --location --request POST 'http://xenforo1.local.tinhte.vn:10080/api/index.php?/threads/65/image&oauth_token=2f537b197d8c9e3db06df795bc323f07c51d39cb' \
--form 'image=other \
--form 'other=https://motosaigon.vn/wp-content/uploads/2019/11/honda-cb1000r-2020-danh-gia-xe-motosaigon-1.jpg' \
--form 'is_cover=1' \
--form 'cover_color=rgb(144,151,118)'




##### Response

Response status 200

```
{
    "status": "ok",
    "message": "Changes Saved",
}
```

Response status 400

```
{
    "errors": [
            "The requested thread could not be found."
    ],
}
```

#### User

# General Permission

User want to update cover to thread must have `bdImage_setCover` permission.


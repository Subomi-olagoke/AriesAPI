
REGISTER REQUEST
{  
"first_name":"Lezie",
 "last_name":"Ikwuegbu",
 "username": "louis",
  "email": "Lou.ikwuegbu3@gmail.com",
  "password": "BeachMaster02$",
  "password_confirmation": "BeachMaster02$"
}


LOGIN REQUEST
{
    "username": "louis",
   "email": "Lou.ikwuegbu3@gmail.com",
  "password": "BeachMaster02$"
}

LOGIN RESPONSE

{
    "user": {
        "id": "9bb2e6e2-8fef-42b8-9f33-a41b0a73896d",
        "first_name": "Lezie",
        "last_name": "Ikwuegbu",
        "username": "louis",
        "role": "explorer",
        "avatar": null,
        "verification_code": null,
        "email": "Lou.ikwuegbu3@gmail.com",
        "email_verified_at": null,
        "api_token": null,
        "created_at": "2024-12-27T23:35:03.000000Z",
        "updated_at": "2024-12-27T23:35:03.000000Z",
        "isAdmin": 0
    },
    "access_token": "1|ehyzr9R8IXZtcMWWxXIH3S5x2lvCI9PrTU8hEMPafda904f5",
    "token_type": "Bearer"
}

/resetPassReq
/resetPassword
/setup
/createPreferences
/followOptions
/logout
/profile/{user:username}
/savePref
/createFollow
/unfollow
/create-course
/course/{id}
/feed
/post
/viewPost

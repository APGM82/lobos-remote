import routes from "../routes.ts"

const apiUrl = routes.usersUrl

const getUsers = async () => {
    try {
        return await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                //'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        throw error
    }
}

export {
    getUsers
}